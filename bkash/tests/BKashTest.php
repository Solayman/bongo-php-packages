<?php

namespace Bongo\Packages\Bkash\Tests;

use Bongo\Packages\Bkash\BKash;
use InvalidArgumentException;
use RuntimeException;
use System\Database\DB;
use System\Database\Schema\Schema;
use System\Events\Event;
use System\Testing\TestCase;

class BKashTest extends TestCase
{
    public function tearDown(): void
    {
        BKash::setMockTransport(null);
    }

    public function testBaseUrlsForSandboxAndLive(): void
    {
        $sandbox = new BKash([
            'app_key'    => 'test_key',
            'app_secret' => 'test_sec',
            'username'   => 'test_user',
            'password'   => 'test_pwd',
            'sandbox'    => true,
        ]);
        $this->assertSame('https://tokenized.sandbox.bka.sh/v1.2.0-beta', $sandbox->getBaseUrl());

        $live = new BKash([
            'app_key'    => 'live_key',
            'app_secret' => 'live_sec',
            'username'   => 'live_user',
            'password'   => 'live_pwd',
            'sandbox'    => false,
        ]);
        $this->assertSame('https://tokenized.pay.bka.sh/v1.2.0-beta', $live->getBaseUrl());
    }

    public function testMissingCredentialsThrows(): void
    {
        $gateway = new BKash([
            'app_key'    => '',
            'app_secret' => '',
            'username'   => '',
            'password'   => '',
        ]);

        $this->assertThrows(RuntimeException::class, function () use ($gateway) {
            $gateway->grantToken();
        });

        $this->assertThrows(RuntimeException::class, function () use ($gateway) {
            $gateway->pay(['amount' => 100]);
        });
    }

    public function testInvalidAmountThrows(): void
    {
        $gateway = new BKash([
            'app_key'    => 'key',
            'app_secret' => 'sec',
            'username'   => 'user',
            'password'   => 'pwd',
        ]);

        $this->assertThrows(InvalidArgumentException::class, function () use ($gateway) {
            $gateway->pay(['amount' => 0]);
        });

        $this->assertThrows(InvalidArgumentException::class, function () use ($gateway) {
            $gateway->pay(['amount' => -25]);
        });
    }

    public function testTokenGrantAndCachingWithMock(): void
    {
        $calls = 0;
        BKash::setMockTransport(function ($url, $method, $data, $headers) use (&$calls) {
            $calls++;
            return [
                'statusCode'    => '0000',
                'statusMessage' => 'Successful',
                'id_token'      => 'token_xyz_123',
                'token_type'    => 'Bearer',
                'expires_in'    => 3600,
            ];
        });

        $gateway = new BKash([
            'app_key'    => 'key',
            'app_secret' => 'sec',
            'username'   => 'user',
            'password'   => 'pwd',
        ]);

        $token1 = $gateway->grantToken();
        $this->assertSame('token_xyz_123', $token1);
        $this->assertSame(1, $calls);

        // Second call should return cached token without invoking transport
        $token2 = $gateway->grantToken();
        $this->assertSame('token_xyz_123', $token2);
        $this->assertSame(1, $calls);
    }

    public function testPaymentCreationWithMock(): void
    {
        BKash::setMockTransport(function ($url, $method, $data, $headers) {
            if (str_contains($url, '/token/grant')) {
                return [
                    'id_token'   => 'token_mock',
                    'expires_in' => 3600,
                ];
            }
            if (str_contains($url, '/checkout/create')) {
                return [
                    'statusCode'            => '0000',
                    'statusMessage'         => 'Successful',
                    'paymentID'             => 'PAY_1001',
                    'bkashURL'              => 'https://tokenized.sandbox.bka.sh/checkout/PAY_1001',
                    'amount'                => $data['amount'],
                    'currency'              => 'BDT',
                    'merchantInvoiceNumber' => $data['merchantInvoiceNumber'],
                ];
            }
            return [];
        });

        $gateway = new BKash([
            'app_key'    => 'key',
            'app_secret' => 'sec',
            'username'   => 'user',
            'password'   => 'pwd',
        ]);

        $res = $gateway->pay([
            'amount'     => 750.50,
            'invoice_no' => 'INV-MOCK-1',
        ]);

        $this->assertSame('SUCCESS', $res['status']);
        $this->assertSame('PAY_1001', $res['payment_id']);
        $this->assertSame('https://tokenized.sandbox.bka.sh/checkout/PAY_1001', $res['gateway_url']);
    }


    /** A gateway whose API answers from $remote: ['execute' => [...], 'query' => [...]] */
    private function gateway(array &$remote): BKash
    {
        BKash::setMockTransport(function ($url, $method, $data, $headers) use (&$remote) {
            if (str_contains($url, '/token/grant')) {
                return ['id_token' => 'token_mock', 'expires_in' => 3600];
            }
            if (str_contains($url, '/checkout/execute')) {
                return $remote['execute'];
            }
            if (str_contains($url, '/payment/status')) {
                return $remote['query'];
            }
            return [];
        });
        return new BKash(['app_key' => 'key', 'app_secret' => 'sec', 'username' => 'user', 'password' => 'pwd']);
    }

    private function seedPayment(string $paymentId, string $status = 'INITIATED'): void
    {
        if (!Schema::hasTable('bkash_transactions')) {
            (require dirname(__DIR__) . '/database/migrations/2026_10_04_000000_create_bkash_transactions_table.php')->up();
        }
        DB::table('bkash_transactions')->where('payment_id', $paymentId)->delete();
        DB::table('bkash_transactions')->insert([
            'payment_id' => $paymentId, 'amount' => 350, 'currency' => 'BDT', 'status' => $status,
            'invoice_no' => 'INV-' . $paymentId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function storedStatus(string $paymentId): string
    {
        return (string) DB::table('bkash_transactions')->where('payment_id', $paymentId)->first()->status;
    }

    /** @return array<string, int> how often each payment event fired */
    private function countEvents(): array
    {
        $counts = ['payment.success' => 0, 'payment.failed' => 0, 'invoice' => ''];
        Event::forget('payment.success');
        Event::forget('payment.failed');
        Event::listen('payment.success', function ($payload) use (&$counts) {
            $counts['payment.success']++;
            $counts['invoice'] = $payload['invoice_no'] ?? '';
        });
        Event::listen('payment.failed', function () use (&$counts) {
            $counts['payment.failed']++;
        });
        $this->events = &$counts;
        return $counts;
    }

    private array $events = [];

    public function testSuccessFiresOnceAndReplaysNeverUndoIt(): void
    {
        $this->seedPayment('PAY_A');
        $paid = ['statusCode' => '0000', 'transactionStatus' => 'Completed', 'trxID' => 'TRX_A', 'amount' => '350.00', 'currency' => 'BDT'];
        $remote = ['execute' => $paid, 'query' => $paid];
        $gateway = $this->gateway($remote);
        $this->countEvents();

        $first = $gateway->verify(['paymentID' => 'PAY_A', 'status' => 'success']);
        $this->assertTrue($first['valid']);
        $this->assertSame('COMPLETED', $this->storedStatus('PAY_A'));
        $this->assertSame(1, $this->events['payment.success']);
        $this->assertSame('INV-PAY_A', $this->events['invoice'], 'the event names the order');

        // bKash refuses a second execute: a replayed success link must not flip the payment to FAILED
        $remote['execute'] = ['statusCode' => '2062', 'statusMessage' => 'The payment has already been completed'];
        $replay = $gateway->verify(['paymentID' => 'PAY_A', 'status' => 'success']);
        $this->assertTrue($replay['valid']);
        $this->assertSame('COMPLETED', $this->storedStatus('PAY_A'));

        // A forged cancel link for a paid order changes nothing
        $gateway->verify(['paymentID' => 'PAY_A', 'status' => 'cancel']);
        $this->assertSame('COMPLETED', $this->storedStatus('PAY_A'));

        $this->assertSame(1, $this->events['payment.success'], 'payment.success fires exactly once');
        $this->assertSame(0, $this->events['payment.failed']);
    }

    public function testCancelOnlyTouchesUnpaidPaymentsAndPaymentCanStillComplete(): void
    {
        $this->seedPayment('PAY_B');
        $remote = ['execute' => [], 'query' => ['statusCode' => '0000', 'transactionStatus' => 'Initiated']];
        $gateway = $this->gateway($remote);
        $this->countEvents();

        $cancelled = $gateway->verify(['paymentID' => 'PAY_B', 'status' => 'cancel']);
        $this->assertFalse($cancelled['valid']);
        $this->assertSame('CANCELLED', $this->storedStatus('PAY_B'));
        $this->assertSame(1, $this->events['payment.failed']);

        // Cancelling twice fires nothing new
        $gateway->verify(['paymentID' => 'PAY_B', 'status' => 'cancel']);
        $this->assertSame(1, $this->events['payment.failed']);

        // The customer pays after all: bKash's answer wins over the earlier cancel
        $remote['execute'] = ['statusCode' => '0000', 'transactionStatus' => 'Completed', 'trxID' => 'TRX_B', 'amount' => '350.00'];
        $this->assertTrue($gateway->verify(['paymentID' => 'PAY_B'])['valid']);
        $this->assertSame('COMPLETED', $this->storedStatus('PAY_B'));
        $this->assertSame(1, $this->events['payment.success']);
    }

    public function testUnfinishedPaymentStaysPending(): void
    {
        $this->seedPayment('PAY_C');
        $remote = [
            'execute' => ['statusCode' => '2056', 'statusMessage' => 'Invalid Payment State'],
            'query'   => ['statusCode' => '0000', 'transactionStatus' => 'Initiated'],
        ];
        $this->countEvents();

        $result = $this->gateway($remote)->verify(['paymentID' => 'PAY_C']);
        $this->assertFalse($result['valid']);
        $this->assertSame('PENDING', $result['status']);
        $this->assertSame('INITIATED', $this->storedStatus('PAY_C'), 'an attacker cannot fail a payment the customer is still making');
        $this->assertSame(0, $this->events['payment.failed']);
    }

    public function testMalformedPaymentIdIsRejected(): void
    {
        $remote = ['execute' => [], 'query' => []];
        $gateway = $this->gateway($remote);
        $this->assertThrows(InvalidArgumentException::class, fn() => $gateway->verify(['paymentID' => '../../x']));
        $this->assertThrows(InvalidArgumentException::class, fn() => $gateway->verify(['paymentID' => '']));
    }
}
