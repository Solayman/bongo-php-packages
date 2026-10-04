<?php

declare(strict_types=1);

namespace Bongo\Packages\Paypal\Tests;

use Bongo\Packages\Paypal\Controllers\PaypalController;
use Bongo\Packages\Paypal\Exceptions\PaypalException;
use Bongo\Packages\Paypal\Paypal;
use InvalidArgumentException;
use System\Events\Event;
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

        $checkoutHtml = $controller->checkout(new Request());
        $this->assertContains('ORDER-MOCK-12345', $checkoutHtml);

        $_GET['token'] = 'ORDER-MOCK-12345';
        $_GET['PayerID'] = 'PAYER-123';

        $successHtml = $controller->success(new Request());
        $this->assertContains('ORDER-MOCK-12345', $successHtml);

        $_GET['token'] = 'ORDER-MOCK-12345';
        $cancelHtml = $controller->cancel(new Request());
        $this->assertContains('Payment Cancelled', $cancelHtml);
    }
}
