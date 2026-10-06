<?php

declare(strict_types=1);

namespace Bongo\Packages\Stripe\Tests;

use Bongo\Packages\Stripe\Controllers\StripeController;
use Bongo\Packages\Stripe\Stripe;
use InvalidArgumentException;
use RuntimeException;
use System\Database\DB;
use System\Database\Schema\Schema;
use System\Events\Event;
use System\Http\HttpException;
use System\Http\Request;
use System\Testing\TestCase;

class StripeTest extends TestCase
{
    public function setUp(): void
    {
        if (class_exists(\System\View\ViewEngine::class)) {
            \System\View\ViewEngine::addNamespace('stripe', dirname(__DIR__) . '/views');
        }
    }

    public function tearDown(): void
    {
        Stripe::setMockTransport(null);
    }

    public function testInitializationAndConfiguration(): void
    {
        $stripe = new Stripe([
            'key'            => 'pk_test_sample',
            'secret'         => 'sk_test_sample',
            'webhook_secret' => 'whsec_sample',
            'currency'       => 'eur',
        ]);

        $this->assertSame('pk_test_sample', $stripe->getKey());
        $this->assertSame('sk_test_sample', $stripe->getSecret());
        $this->assertSame('whsec_sample', $stripe->getWebhookSecret());
        $this->assertSame('eur', $stripe->getCurrency());
    }

    public function testMissingSecretKeyThrows(): void
    {
        $stripe = new Stripe([
            'secret' => '',
        ]);

        $this->assertThrows(InvalidArgumentException::class, function () use ($stripe) {
            $stripe->createCheckoutSession([
                'amount'      => 10.00,
                'success_url' => 'http://localhost/success',
                'cancel_url'  => 'http://localhost/cancel',
            ]);
        });
    }

    public function testZeroDecimalAmountCalculation(): void
    {
        $stripe = new Stripe(['secret' => 'sk_test_sample']);

        // Standard currency (USD): $25.50 -> 2550 cents
        $this->assertSame(2550, $stripe->toZeroDecimalAmount(25.50, 'usd'));
        $this->assertSame(10000, $stripe->toZeroDecimalAmount(100.00, 'eur'));

        // Zero-decimal currency (JPY): ¥5000 -> 5000
        $this->assertSame(5000, $stripe->toZeroDecimalAmount(5000.00, 'jpy'));
        $this->assertSame(1500, $stripe->toZeroDecimalAmount(1500.00, 'krw'));
    }

    public function testCreateCheckoutSessionWithMock(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedParams = [];

        Stripe::setMockTransport(function (string $method, string $url, array $params) use (&$capturedMethod, &$capturedUrl, &$capturedParams): array {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedParams = $params;

            return [
                'id'             => 'cs_test_abc123',
                'object'         => 'checkout.session',
                'url'            => 'https://checkout.stripe.com/c/pay/cs_test_abc123',
                'payment_status' => 'unpaid',
            ];
        });

        $stripe = new Stripe([
            'secret'      => 'sk_test_mock',
            'success_url' => 'http://localhost/success',
            'cancel_url'  => 'http://localhost/cancel',
        ]);

        $res = $stripe->createCheckoutSession([
            'amount'         => 35.00,
            'currency'       => 'usd',
            'name'           => 'Test Course',
            'customer_email' => 'student@example.com',
            'metadata'       => ['course_id' => 99],
        ]);

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('https://api.stripe.com/v1/checkout/sessions', $capturedUrl);
        $this->assertSame('payment', $capturedParams['mode']);
        $this->assertSame('student@example.com', $capturedParams['customer_email']);
        $this->assertSame(3500, $capturedParams['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('cs_test_abc123', $res['id']);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_abc123', $res['url']);
    }

    public function testCreatePaymentIntentWithMock(): void
    {
        $capturedParams = [];

        Stripe::setMockTransport(function (string $method, string $url, array $params) use (&$capturedParams): array {
            $capturedParams = $params;

            return [
                'id'            => 'pi_mock_123',
                'object'        => 'payment_intent',
                'amount'        => 5000,
                'currency'      => 'usd',
                'status'        => 'requires_payment_method',
                'client_secret' => 'pi_mock_123_secret_xyz',
            ];
        });

        $stripe = new Stripe(['secret' => 'sk_test_mock']);
        $res = $stripe->createPaymentIntent([
            'amount'        => 50.00,
            'currency'      => 'usd',
            'receipt_email' => 'client@example.com',
        ]);

        $this->assertSame(5000, $capturedParams['amount']);
        $this->assertSame('client@example.com', $capturedParams['receipt_email']);
        $this->assertSame('pi_mock_123', $res['id']);
        $this->assertSame('pi_mock_123_secret_xyz', $res['client_secret']);
    }

    public function testRetrievePaymentIntentAndSessionWithMock(): void
    {
        Stripe::setMockTransport(function (string $method, string $url): array {
            if (str_contains($url, '/payment_intents/pi_find_123')) {
                return ['id' => 'pi_find_123', 'status' => 'succeeded'];
            }
            if (str_contains($url, '/checkout/sessions/cs_find_456')) {
                return ['id' => 'cs_find_456', 'payment_status' => 'paid'];
            }
            return [];
        });

        $stripe = new Stripe(['secret' => 'sk_test_mock']);
        $intent = $stripe->retrievePaymentIntent('pi_find_123');
        $this->assertSame('succeeded', $intent['status']);

        $session = $stripe->retrieveCheckoutSession('cs_find_456');
        $this->assertSame('paid', $session['payment_status']);
    }

    public function testRefundWithMock(): void
    {
        $capturedParams = [];

        Stripe::setMockTransport(function (string $method, string $url, array $params) use (&$capturedParams): array {
            $capturedParams = $params;
            return [
                'id'     => 're_mock_789',
                'status' => 'succeeded',
                'amount' => $params['amount'] ?? 2000,
            ];
        });

        $stripe = new Stripe(['secret' => 'sk_test_mock']);
        $refund = $stripe->refund('pi_123', 20.00, 'requested_by_customer');

        $this->assertSame('pi_123', $capturedParams['payment_intent']);
        $this->assertSame(2000, $capturedParams['amount']);
        $this->assertSame('requested_by_customer', $capturedParams['reason']);
        $this->assertSame('succeeded', $refund['status']);
    }

    public function testWebhookSignatureVerificationSuccess(): void
    {
        $secret = 'whsec_test_secret_123';
        $payload = json_encode([
            'id'   => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'             => 'cs_test_completed',
                    'payment_status' => 'paid',
                    'amount_total'   => 4500,
                    'currency'       => 'usd',
                ],
            ],
        ]);

        $timestamp = (string) time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $sigHeader = "t={$timestamp},v1={$signature}";

        $stripe = new Stripe([
            'secret'         => 'sk_test_sample',
            'webhook_secret' => $secret,
        ]);

        $event = $stripe->verifyWebhook($payload, $sigHeader, $secret);

        $this->assertSame('evt_test_123', $event['id']);
        $this->assertSame('checkout.session.completed', $event['type']);
        $this->assertSame('cs_test_completed', $event['data']['object']['id']);
    }

    public function testWebhookSignatureVerificationRejectsInvalidSignature(): void
    {
        $secret = 'whsec_test_secret_123';
        $payload = json_encode(['type' => 'test.event']);
        $timestamp = (string) time();
        $sigHeader = "t={$timestamp},v1=bad_forged_signature_hash";

        $stripe = new Stripe(['webhook_secret' => $secret]);

        $this->assertThrows(RuntimeException::class, function () use ($stripe, $payload, $sigHeader, $secret) {
            $stripe->verifyWebhook($payload, $sigHeader, $secret);
        });
    }

    public function testWebhookSignatureVerificationRejectsExpiredTimestamp(): void
    {
        $secret = 'whsec_test_secret_123';
        $payload = json_encode(['type' => 'test.event']);
        // 400 seconds ago (tolerance is 300 seconds)
        $timestamp = (string) (time() - 400);
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $sigHeader = "t={$timestamp},v1={$signature}";

        $stripe = new Stripe(['webhook_secret' => $secret]);

        $this->assertThrows(RuntimeException::class, function () use ($stripe, $payload, $sigHeader, $secret) {
            $stripe->verifyWebhook($payload, $sigHeader, $secret, 300);
        });
    }

    public function testStripeControllerHandlesCallbackAndViews(): void
    {
        $controller = new StripeController();

        // Cancel view
        $cancelOutput = $controller->cancel(new Request());
        $this->assertContains('stripe-cancel-card', $cancelOutput);

        // Success view
        $_GET['session_id'] = 'cs_test_rendered';
        $successOutput = $controller->success(new Request());
        $this->assertContains('stripe-success-card', $successOutput);
        $this->assertContains('cs_test_rendered', $successOutput);

        $_GET = [];
    }

    private array $events = ['payment.success' => 0, 'payment.failed' => 0];

    private function listen(): void
    {
        foreach (array_keys($this->events) as $name) {
            $this->events[$name] = 0;
            Event::forget($name);
            Event::listen($name, function () use ($name) {
                $this->events[$name]++;
            });
        }
    }

    /** Send a signed webhook; a fresh event id unless one is given (retries reuse it) */
    private function deliver(string $type, array $object, ?string $eventId = null): string
    {
        $secret = 'whsec_idempotency';
        $eventId ??= 'evt_' . bin2hex(random_bytes(6));
        $payload = (string) json_encode(['id' => $eventId, 'type' => $type, 'data' => ['object' => $object]]);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
        (new Stripe(['secret' => 'sk_test', 'webhook_secret' => $secret]))->verifyWebhook($payload, "t={$timestamp},v1={$signature}", $secret);
        return $eventId;
    }

    private function seed(array $row): void
    {
        if (!Schema::hasTable('stripe_payments')) {
            (require glob(dirname(__DIR__) . '/database/migrations/*.php')[0])->up();
        }
        DB::table('stripe_payments')->insert($row + ['amount' => 45, 'currency' => 'usd', 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function status(string $column, string $id): string
    {
        return (string) DB::table('stripe_payments')->where($column, $id)->first()->status;
    }

    public function testCheckoutPaymentFiresSuccessOnce(): void
    {
        $session = 'cs_' . bin2hex(random_bytes(6));
        $intent = 'pi_' . bin2hex(random_bytes(6));
        $this->seed(['session_id' => $session]);
        $this->listen();

        $paid = ['id' => $session, 'payment_status' => 'paid', 'payment_intent' => $intent, 'amount_total' => 4500, 'currency' => 'usd', 'metadata' => ['order_id' => 'ORD_9']];
        $eventId = $this->deliver('checkout.session.completed', $paid);
        $this->deliver('checkout.session.completed', $paid, $eventId);                     // Stripe retry
        $this->deliver('payment_intent.succeeded', ['id' => $intent, 'amount' => 4500]);    // the twin event
        $this->deliver('checkout.session.completed', $paid);                               // replay with another id
        $this->deliver('payment_intent.payment_failed', ['id' => $intent]);                // late failure

        $this->assertSame(1, $this->events['payment.success'], 'one checkout, one payment.success');
        $this->assertSame(0, $this->events['payment.failed']);
        $this->assertSame('paid', $this->status('session_id', $session), 'a paid payment is never marked failed');
    }

    public function testDelayedPaymentWaitsUntilTheMoneyArrives(): void
    {
        $session = 'cs_' . bin2hex(random_bytes(6));
        $this->seed(['session_id' => $session]);
        $this->listen();

        $this->deliver('checkout.session.completed', ['id' => $session, 'payment_status' => 'unpaid', 'amount_total' => 4500]);
        $this->assertSame(0, $this->events['payment.success'], 'a bank debit that has not cleared is not a payment');
        $this->assertSame('processing', $this->status('session_id', $session));

        $this->deliver('checkout.session.async_payment_succeeded', ['id' => $session, 'payment_status' => 'paid', 'amount_total' => 4500]);
        $this->assertSame(1, $this->events['payment.success']);
        $this->assertSame('paid', $this->status('session_id', $session));
    }

    public function testDirectPaymentIntentFiresOnce(): void
    {
        $intent = 'pi_' . bin2hex(random_bytes(6));
        $this->seed(['payment_intent_id' => $intent]);
        $this->listen();

        $this->deliver('payment_intent.succeeded', ['id' => $intent, 'amount' => 4500]);
        $this->deliver('payment_intent.succeeded', ['id' => $intent, 'amount' => 4500]);
        $this->assertSame(1, $this->events['payment.success']);
        $this->assertSame('succeeded', $this->status('payment_intent_id', $intent));
    }

    public function testDemoCheckoutRouteIsOffByDefault(): void
    {
        $_POST = ['amount' => '0.50', 'order_id' => 'ORD_EXPENSIVE'];
        $this->assertThrows(HttpException::class, fn() => (new StripeController())->checkout(new Request()));
        $_POST = [];
    }
}
