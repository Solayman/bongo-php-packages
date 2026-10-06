<?php

declare(strict_types=1);

namespace Bongo\Packages\Paypal\Tests;

use Bongo\Packages\Paypal\Controllers\PaypalController;
use Bongo\Packages\Paypal\Exceptions\PaypalException;
use Bongo\Packages\Paypal\Paypal;
use InvalidArgumentException;
use System\Database\DB;
use System\Database\Schema\Schema;
use System\Events\Event;
use System\Http\HttpException;
use System\Http\Request;
use System\Testing\TestCase;

class PaypalTest extends TestCase
{
    public function setUp(): void
    {
        Paypal::setMockTransport(null);
        if (class_exists(\System\View\ViewEngine::class)) {
            \System\View\ViewEngine::addNamespace('paypal', dirname(__DIR__) . '/views');
        }
    }

    public function tearDown(): void
    {
        Paypal::setMockTransport(null);
        $_POST = [];
        $_GET = [];
    }

    public function testInitializationAndConfiguration(): void
    {
        $paypal = new Paypal([
            'mode'          => 'live',
            'client_id'     => 'test_client_id_123',
            'client_secret' => 'test_client_secret_456',
            'currency'      => 'EUR',
            'brand_name'    => 'Acme Store',
        ]);

        $this->assertSame('live', $paypal->getMode());
        $this->assertSame('test_client_id_123', $paypal->getClientId());
        $this->assertSame('EUR', $paypal->getCurrency());
        $this->assertSame('https://api-m.paypal.com', $paypal->getBaseUrl());
    }

    public function testMissingCredentialsThrows(): void
    {
        $paypal = new Paypal([
            'client_id'     => '',
            'client_secret' => '',
        ]);

        $this->assertThrows(InvalidArgumentException::class, function () use ($paypal) {
            $paypal->getAccessToken();
        });
    }

    public function testZeroOrNegativeAmountThrows(): void
    {
        $paypal = new Paypal([
            'client_id'     => 'id',
            'client_secret' => 'secret',
        ]);

        $this->assertThrows(InvalidArgumentException::class, function () use ($paypal) {
            $paypal->createOrder(['amount' => 0]);
        });

        $this->assertThrows(InvalidArgumentException::class, function () use ($paypal) {
            $paypal->createOrder(['amount' => -15.50]);
        });
    }

    public function testOAuthAccessTokenFetchingWithMock(): void
    {
        $callCount = 0;
        Paypal::setMockTransport(function (string $method, string $url, $body, array $headers) use (&$callCount): array {
            $callCount++;
            return [
                'access_token' => 'mock_token_abc123',
                'token_type'   => 'Bearer',
                'expires_in'   => 3600,
            ];
        });

        $paypal = new Paypal([
            'client_id'     => 'mock_client',
            'client_secret' => 'mock_secret',
        ]);

        $token1 = $paypal->getAccessToken();
        $this->assertSame('mock_token_abc123', $token1);
        $this->assertSame(1, $callCount);

        // Subsequent call should use memory cached token
        $token2 = $paypal->getAccessToken();
        $this->assertSame('mock_token_abc123', $token2);
        $this->assertSame(1, $callCount, 'Access token should be cached in memory');
    }

    public function testCreateOrderWithMock(): void
    {
        $capturedRequests = [];
        Paypal::setMockTransport(function (string $method, string $url, $body, array $headers) use (&$capturedRequests): array {
            $capturedRequests[] = ['method' => $method, 'url' => $url, 'body' => $body];

            if (str_contains($url, '/token')) {
                return ['access_token' => 'mock_token', 'expires_in' => 3600];
            }

            return [
                'id'     => 'ORDER-XYZ-789',
                'status' => 'CREATED',
                'links'  => [
                    [
                        'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-XYZ-789',
                        'rel'  => 'approve',
                    ],
                ],
            ];
        });

        $paypal = new Paypal([
            'client_id'     => 'mock_client',
            'client_secret' => 'mock_secret',
        ]);

        $order = $paypal->createOrder([
            'amount'       => 45.50,
            'currency'     => 'USD',
            'description'  => 'Book Purchase',
            'reference_id' => 'REF-001',
        ]);

        $this->assertSame('ORDER-XYZ-789', $order['id']);
        $this->assertSame('CREATED', $order['status']);

        $approvalUrl = $paypal->getApprovalUrl($order);
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-XYZ-789', $approvalUrl);

        $orderReq = $capturedRequests[1];
        $this->assertSame('POST', $orderReq['method']);
        $bodyData = json_decode($orderReq['body'], true);
        $this->assertSame('45.50', $bodyData['purchase_units'][0]['amount']['value']);
        $this->assertSame('USD', $bodyData['purchase_units'][0]['amount']['currency_code']);
        $this->assertSame('REF-001', $bodyData['purchase_units'][0]['reference_id']);
    }

    public function testCaptureOrderWithMock(): void
    {
        $dispatchedEvents = [];
        if (class_exists(Event::class)) {
            Event::listen('payment.success', function ($payload) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $payload;
            });
        }

        Paypal::setMockTransport(function (string $method, string $url, $body, array $headers): array {
            if (str_contains($url, '/token')) {
                return ['access_token' => 'mock_token', 'expires_in' => 3600];
            }

            return [
                'id'     => 'ORDER-XYZ-789',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    [
                        'reference_id' => 'default',
                        'payments' => [
                            'captures' => [
                                [
                                    'id'     => 'CAP-998877',
                                    'status' => 'COMPLETED',
                                    'amount' => ['currency_code' => 'USD', 'value' => '45.50'],
                                ],
                            ],
                        ],
                    ],
                ],
                'payer' => [
                    'email_address' => 'buyer@example.com',
                    'payer_id'      => 'PAYER-999',
                    'name'          => ['given_name' => 'Alice', 'surname' => 'Smith'],
                ],
            ];
        });

        $paypal = new Paypal([
            'client_id'     => 'mock_client',
            'client_secret' => 'mock_secret',
        ]);

        $res = $paypal->captureOrder('ORDER-XYZ-789');
        $this->assertSame('COMPLETED', $res['status']);
        $this->assertSame('CAP-998877', $res['purchase_units'][0]['payments']['captures'][0]['id']);

        if (class_exists(Event::class)) {
            $this->assertCount(1, $dispatchedEvents);
            $this->assertSame('paypal', $dispatchedEvents[0]['gateway']);
            $this->assertSame('CAP-998877', $dispatchedEvents[0]['capture_id']);
            $this->assertSame(45.50, $dispatchedEvents[0]['amount']);
            $this->assertSame('Alice Smith', $dispatchedEvents[0]['payer_name']);
        }
    }

    public function testRefundWithMock(): void
    {
        $dispatchedRefunds = [];
        if (class_exists(Event::class)) {
            Event::listen('payment.refunded', function ($payload) use (&$dispatchedRefunds) {
                $dispatchedRefunds[] = $payload;
            });
        }

        Paypal::setMockTransport(function (string $method, string $url, $body, array $headers): array {
            if (str_contains($url, '/token')) {
                return ['access_token' => 'mock_token', 'expires_in' => 3600];
            }

            return [
                'id'     => 'REF-554433',
                'status' => 'COMPLETED',
                'amount' => ['currency_code' => 'USD', 'value' => '20.00'],
            ];
        });

        $paypal = new Paypal([
            'client_id'     => 'mock_client',
            'client_secret' => 'mock_secret',
        ]);

        $res = $paypal->refund('CAP-998877', 20.00, 'USD', 'Customer return');
        $this->assertSame('REF-554433', $res['id']);
        $this->assertSame('COMPLETED', $res['status']);

        if (class_exists(Event::class)) {
            $this->assertCount(1, $dispatchedRefunds);
            $this->assertSame('CAP-998877', $dispatchedRefunds[0]['capture_id']);
            $this->assertSame(20.00, $dispatchedRefunds[0]['amount']);
        }
    }

    public function testFakeHelperAndStaticFacade(): void
    {
        Paypal::fake();

        $order = Paypal::create([
            'amount'      => 99.00,
            'currency'    => 'USD',
            'description' => 'Annual Plan',
        ]);

        $this->assertSame('ORDER-MOCK-12345', $order['id']);
        $this->assertSame('CREATED', $order['status']);

        $url = Paypal::approvalUrl($order);
        $this->assertContains('ORDER-MOCK-12345', $url);

        $capture = Paypal::capture('ORDER-MOCK-12345');
        $this->assertSame('COMPLETED', $capture['status']);

        $refund = Paypal::issueRefund('CAPTURE-MOCK-67890', 50.00);
        $this->assertSame('COMPLETED', $refund['status']);
    }

    public function testWebhookVerification(): void
    {
        Paypal::setMockTransport(function (string $method, string $url, $body, array $headers): array {
            if (str_contains($url, '/token')) {
                return ['access_token' => 'mock_token', 'expires_in' => 3600];
            }
            return ['verification_status' => 'SUCCESS'];
        });

        $paypal = new Paypal([
            'client_id'     => 'mock_client',
            'client_secret' => 'mock_secret',
            'webhook_id'    => 'WH-12345',
        ]);

        $headers = [
            'paypal-auth-algo'         => 'SHA256withRSA',
            'paypal-cert-url'          => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-123',
            'paypal-transmission-id'   => 'trans-123',
            'paypal-transmission-sig'  => 'sig-456',
            'paypal-transmission-time' => '2026-10-04T12:00:00Z',
        ];

        $rawBody = json_encode(['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'CAP-1']]);
        $verified = $paypal->verifyWebhook($headers, $rawBody);
        $this->assertTrue($verified);

        // Missing header should fail
        $invalidHeaders = $headers;
        unset($invalidHeaders['paypal-transmission-sig']);
        $this->assertFalse($paypal->verifyWebhook($invalidHeaders, $rawBody));
    }

    public function testControllerCheckoutAndSuccess(): void
    {
        Paypal::fake();

        $controller = new PaypalController();

        $_POST['amount'] = 30.00;
        $_POST['currency'] = 'USD';
        $_POST['description'] = 'Test Item';

        // The demo checkout (browser chooses the amount) is off unless enabled
        $this->assertThrows(HttpException::class, fn() => $controller->checkout(new Request()));

        $GLOBALS['config']['paypal']['demo_checkout'] = true;
        try {
            $checkoutHtml = $controller->checkout(new Request());
        } finally {
            unset($GLOBALS['config']['paypal']['demo_checkout']);
        }
        $this->assertContains('ORDER-MOCK-12345', $checkoutHtml);

        $_GET['token'] = 'ORDER-MOCK-12345';
        $_GET['PayerID'] = 'PAYER-123';

        $successHtml = $controller->success(new Request());
        $this->assertContains('ORDER-MOCK-12345', $successHtml);

        $_GET['token'] = 'ORDER-MOCK-12345';
        $cancelHtml = $controller->cancel(new Request());
        $this->assertContains('Payment Cancelled', $cancelHtml);
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

    /** A sandbox where every order/capture call answers for $orderId */
    private function sandbox(string $orderId): Paypal
    {
        Paypal::setMockTransport(function (string $method, string $url, $body, array $headers) use ($orderId): array {
            if (str_contains($url, '/token')) {
                return ['access_token' => 'mock_token', 'expires_in' => 3600];
            }
            if (str_contains($url, '/verify-webhook-signature')) {
                return ['verification_status' => 'SUCCESS'];
            }
            return [
                'id' => $orderId,
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-' . $orderId, 'amount' => ['currency_code' => 'USD', 'value' => '25.00']]]]]],
            ];
        });
        return new Paypal(['client_id' => 'c', 'client_secret' => 's', 'webhook_id' => 'WH-1']);
    }

    private function webhook(Paypal $paypal, string $type, string $orderId, ?string $eventId = null): void
    {
        $headers = [
            'paypal-auth-algo' => 'SHA256withRSA',
            'paypal-cert-url' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-123',
            'paypal-transmission-id' => 'trans-123',
            'paypal-transmission-sig' => 'sig-456',
            'paypal-transmission-time' => '2026-10-04T12:00:00Z',
        ];
        $paypal->handleWebhook($headers, (string) json_encode([
            'id' => $eventId ?? 'WH-EVT-' . bin2hex(random_bytes(6)),
            'event_type' => $type,
            'resource' => [
                'id' => 'CAP-' . $orderId,
                'amount' => ['currency_code' => 'USD', 'value' => '25.00'],
                'supplementary_data' => ['related_ids' => ['order_id' => $orderId]],
            ],
        ]));
    }

    public function testCaptureAndWebhookReportThePaymentOnce(): void
    {
        $orderId = 'ORD-' . strtoupper(bin2hex(random_bytes(6)));
        if (!Schema::hasTable('paypal_orders')) {
            (require glob(dirname(__DIR__) . '/database/migrations/*.php')[0])->up();
        }
        DB::table('paypal_orders')->insert(['order_id' => $orderId, 'status' => 'CREATED', 'amount' => 25, 'currency' => 'USD', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $paypal = $this->sandbox($orderId);
        $this->listen();

        $paypal->captureOrder($orderId);                                  // return page
        $paypal->captureOrder($orderId);                                  // customer refreshes it
        $this->webhook($paypal, 'PAYMENT.CAPTURE.COMPLETED', $orderId);   // PayPal's webhook for the same capture
        $this->webhook($paypal, 'PAYMENT.CAPTURE.DENIED', $orderId);      // a late denial

        $this->assertSame(1, $this->events['payment.success']);
        $this->assertSame(0, $this->events['payment.failed']);
        $this->assertSame('COMPLETED', (string) DB::table('paypal_orders')->where('order_id', $orderId)->first()->status);
    }

    public function testWebhookRetriesAreHandledOnce(): void
    {
        $orderId = 'ORD-' . strtoupper(bin2hex(random_bytes(6)));
        $paypal = $this->sandbox($orderId);
        $this->listen();

        $this->webhook($paypal, 'PAYMENT.CAPTURE.COMPLETED', $orderId, 'WH-EVT-RETRY-' . $orderId);
        $this->webhook($paypal, 'PAYMENT.CAPTURE.COMPLETED', $orderId, 'WH-EVT-RETRY-' . $orderId);
        $this->assertSame(1, $this->events['payment.success']);
    }

    public function testIdsInApiPathsAreValidated(): void
    {
        $paypal = $this->sandbox('ORD-1');
        $this->assertThrows(PaypalException::class, fn() => $paypal->captureOrder('../../v1/payments/payouts'));
        $this->assertThrows(PaypalException::class, fn() => $paypal->refund('CAP-1/../../x'));
        $this->assertThrows(PaypalException::class, fn() => $paypal->getOrder('ORD 1?x'));
    }
}
