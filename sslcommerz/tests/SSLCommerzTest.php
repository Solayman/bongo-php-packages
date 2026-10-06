<?php

namespace Bongo\Packages\Sslcommerz\Tests;

use Bongo\Packages\Sslcommerz\SSLCommerz;
use RuntimeException;
use System\Database\DB;
use System\Database\Schema\Schema;
use System\Events\Event;
use System\Testing\TestCase;

class SSLCommerzTest extends TestCase
{
    public function testBaseUrlsForSandboxAndLive(): void
    {
        $sandboxGateway = new SSLCommerz([
            'store_id' => 'test_store',
            'store_password' => 'test_passwd',
            'sandbox' => true,
        ]);
        $this->assertTrue($sandboxGateway->isSandbox());
        $this->assertSame('https://sandbox.sslcommerz.com', $sandboxGateway->getBaseUrl());

        $liveGateway = new SSLCommerz([
            'store_id' => 'live_store',
            'store_password' => 'live_passwd',
            'sandbox' => false,
        ]);
        $this->assertFalse($liveGateway->isSandbox());
        $this->assertSame('https://securepay.sslcommerz.com', $liveGateway->getBaseUrl());
    }

    public function testMissingCredentialsThrows(): void
    {
        $gateway = new SSLCommerz([
            'store_id' => '',
            'store_password' => '',
        ]);

        $this->assertThrows(RuntimeException::class, function () use ($gateway) {
            $gateway->initiate(['amount' => 100]);
        });
    }

    public function testInvalidAmountThrows(): void
    {
        $gateway = new SSLCommerz([
            'store_id' => 'test_id',
            'store_password' => 'test_pw',
        ]);

        $this->assertThrows(RuntimeException::class, function () use ($gateway) {
            $gateway->initiate(['amount' => 0]);
        });

        $this->assertThrows(RuntimeException::class, function () use ($gateway) {
            $gateway->initiate(['amount' => -50]);
        });
    }

    public function testMissingValIdOnVerifyThrows(): void
    {
        $gateway = new SSLCommerz([
            'store_id' => 'test_id',
            'store_password' => 'test_pw',
        ]);

        $this->assertThrows(RuntimeException::class, function () use ($gateway) {
            $gateway->verify(['tran_id' => 'TXN_123']);
        });
    }

    /** A gateway whose validator API answers with $answer */
    private function gateway(): SSLCommerz
    {
        return new class (['store_id' => 'store', 'store_password' => 'pass']) extends SSLCommerz {
            public static array $answer = [];

            protected function getHttp(string $url): string
            {
                return (string) json_encode(self::$answer);
            }
        };
    }

    private function seed(string $tranId, float $amount, string $currency = 'BDT'): void
    {
        if (!Schema::hasTable('sslcommerz_transactions')) {
            (require glob(dirname(__DIR__) . '/database/migrations/*.php')[0])->up();
        }
        DB::table('sslcommerz_transactions')->where('tran_id', $tranId)->delete();
        DB::table('sslcommerz_transactions')->insert([
            'tran_id' => $tranId, 'amount' => $amount, 'currency' => $currency, 'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function stored(string $tranId): string
    {
        return (string) DB::table('sslcommerz_transactions')->where('tran_id', $tranId)->first()->status;
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

    public function tearDown(): void
    {
        Event::forget('payment.success');
        Event::forget('payment.failed');
    }

    public function testReplayedSuccessCreditsOnce(): void
    {
        $this->seed('TXN_A', 1200);
        $this->listen();
        $gateway = $this->gateway();
        $gateway::$answer = ['status' => 'VALID', 'tran_id' => 'TXN_A', 'amount' => '1200.00', 'currency' => 'BDT', 'bank_tran_id' => 'B1'];

        $this->assertTrue($gateway->verify(['val_id' => 'VAL_A', 'tran_id' => 'TXN_A'])['valid']);
        $this->assertSame('valid', $this->stored('TXN_A'));

        // The customer (or anyone) posts the same success data again
        $gateway::$answer['status'] = 'VALIDATED';
        $this->assertTrue($gateway->verify(['val_id' => 'VAL_A', 'tran_id' => 'TXN_A'])['valid']);
        $gateway->verify(['val_id' => 'VAL_A']);

        $this->assertSame(1, $this->events['payment.success'], 'payment.success fires once per transaction');
        $this->assertSame('valid', $this->stored('TXN_A'));
    }

    public function testCheapPaymentCannotPayForAnExpensiveOrder(): void
    {
        $this->seed('TXN_BIG', 50000);
        $this->listen();
        $gateway = $this->gateway();

        // Validator confirms a payment of 10 for TXN_BIG: amount does not match the order
        $gateway::$answer = ['status' => 'VALID', 'tran_id' => 'TXN_BIG', 'amount' => '10.00', 'currency' => 'BDT'];
        $result = $gateway->verify(['val_id' => 'VAL_CHEAP', 'tran_id' => 'TXN_BIG']);
        $this->assertFalse($result['valid']);
        $this->assertSame('AMOUNT_MISMATCH', $result['status']);

        // Right amount, wrong currency
        $this->seed('TXN_USD', 100, 'BDT');
        $gateway::$answer = ['status' => 'VALID', 'tran_id' => 'TXN_USD', 'amount' => '100.00', 'currency' => 'USD'];
        $this->assertFalse($gateway->verify(['val_id' => 'VAL_USD'])['valid']);

        // A real val_id of another transaction posted with this order's tran_id
        $gateway::$answer = ['status' => 'VALID', 'tran_id' => 'TXN_OTHER', 'amount' => '50000.00', 'currency' => 'BDT'];
        $mixed = $gateway->verify(['val_id' => 'VAL_OTHER', 'tran_id' => 'TXN_BIG']);
        $this->assertFalse($mixed['valid']);
        $this->assertSame('TRAN_ID_MISMATCH', $mixed['status']);

        $this->assertSame(0, $this->events['payment.success']);
        $this->assertSame('failed', $this->stored('TXN_BIG'), 'a mismatched payment never marks the order paid');
    }

    public function testFailureNeverUndoesAPayment(): void
    {
        $this->seed('TXN_PAID', 300);
        $this->listen();
        $gateway = $this->gateway();
        $gateway::$answer = ['status' => 'VALID', 'tran_id' => 'TXN_PAID', 'amount' => '300', 'currency' => 'BDT'];
        $gateway->verify(['val_id' => 'VAL_P']);

        $gateway::$answer = ['status' => 'INVALID_TRANSACTION', 'tran_id' => 'TXN_PAID'];
        $gateway->verify(['val_id' => 'VAL_P2']);
        $this->assertSame('valid', $this->stored('TXN_PAID'));
        $this->assertSame(0, $this->events['payment.failed']);
    }

    public function testMalformedValIdIsRejected(): void
    {
        $gateway = $this->gateway();
        $this->assertThrows(RuntimeException::class, fn() => $gateway->verify(['val_id' => 'x&store_passwd=leak']));
    }

    public function testFailAndCancelPagesRender(): void
    {
        \System\View\ViewEngine::addNamespace('sslcommerz', dirname(__DIR__) . '/views');
        $_POST = ['tran_id' => 'TXN_PAGE_1', 'error' => 'Call this number for a refund'];
        try {
            $controller = new \Bongo\Packages\Sslcommerz\Controllers\SSLCommerzController();
            $fail = $controller->fail(new \System\Http\Request());
            $this->assertContains('TXN_PAGE_1', $fail);
            $this->assertNotContains('Call this number', $fail, 'posted text is never shown back');
            $this->assertContains('sslcommerz-cancel', $controller->cancel(new \System\Http\Request()));
        } finally {
            $_POST = [];
        }
    }
}
