<?php

declare(strict_types=1);

namespace Bongo\Packages\Aamarpay\Tests;

use Bongo\Packages\Aamarpay\AamarPay;
use Bongo\Packages\Aamarpay\Controllers\AamarPayController;
use RuntimeException;
use System\Database\DB;
use System\Database\Schema\Schema;
use System\Events\Event;
use System\Http\Request;
use System\Testing\TestCase;
use System\View\ViewEngine;

class AamarPayTest extends TestCase
{
    public function setUp(): void
    {
        if (class_exists(ViewEngine::class)) {
            ViewEngine::addNamespace('aamarpay', dirname(__DIR__) . '/views');
        }
    }

    public function testConfigAndEnvironmentResolution(): void
    {
        $gateway = new AamarPay([
            'store_id'      => 'custom_merchant',
            'signature_key' => 'custom_sig_key_123',
            'sandbox'       => true,
            'currency'      => 'BDT',
        ]);

        $this->assertSame('custom_merchant', $gateway->getStoreId());
        $this->assertTrue($gateway->isSandbox());
        $this->assertSame('https://sandbox.aamarpay.com', $gateway->getBaseUrl());

        $liveGateway = new AamarPay([
            'store_id'      => 'live_merchant',
            'signature_key' => 'live_sig_key_123',
            'sandbox'       => false,
        ]);
        $this->assertFalse($liveGateway->isSandbox());
        $this->assertSame('https://secure.aamarpay.com', $liveGateway->getBaseUrl());
    }

    public function testMissingCredentialsThrowsException(): void
    {
        $this->assertThrows(RuntimeException::class, function () {
            $gateway = new AamarPay([
                'store_id'      => '',
                'signature_key' => '',
            ]);
            $gateway->initiate(['amount' => 100]);
        });
    }

    public function testInvalidAmountThrowsException(): void
    {
        $this->assertThrows(RuntimeException::class, function () {
            $gateway = new AamarPay([
                'store_id'      => 'demo',
                'signature_key' => 'key',
            ]);
            $gateway->initiate(['amount' => 0]);
        });
    }

    /** A gateway whose trxcheck API answers with $answer */
    private function gateway(): AamarPay
    {
        return new class (['store_id' => 'demo', 'signature_key' => 'key']) extends AamarPay {
            public static array $answer = [];

            protected function getJson(string $url): ?array
            {
                return self::$answer;
            }
        };
    }

    private function seed(string $tranId, float $amount): void
    {
        if (!Schema::hasTable('aamarpay_transactions')) {
            (require glob(dirname(__DIR__) . '/database/migrations/*.php')[0])->up();
        }
        DB::table('aamarpay_transactions')->where('tran_id', $tranId)->delete();
        DB::table('aamarpay_transactions')->insert(['tran_id' => $tranId, 'amount' => $amount, 'currency' => 'BDT', 'status' => 'INITIATED', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function testIsSuccessAsksAamarPayInsteadOfTrustingThePost(): void
    {
        $gateway = $this->gateway();
        $gateway::$answer = ['pay_status' => 'Failed'];
        $this->assertFalse($gateway->isSuccess(['pay_status' => 'Successful', 'mer_txnid' => 'AP_FORGED']), 'a forged post is not a payment');

        $gateway::$answer = ['pay_status' => 'Successful', 'amount' => '100', 'currency' => 'BDT'];
        $this->assertTrue($gateway->isSuccess(['mer_txnid' => 'AP_REAL']));
        $this->assertFalse($gateway->isSuccess([]));
    }

    public function testPaymentMustCoverTheOrderAndIsReportedOnce(): void
    {
        $events = 0;
        Event::forget('payment.success');
        Event::listen('payment.success', function () use (&$events) {
            $events++;
        });
        $gateway = $this->gateway();

        $this->seed('AP_SHORT', 5000);
        $gateway::$answer = ['pay_status' => 'Successful', 'amount' => '50', 'currency' => 'BDT'];
        $short = $gateway->verify('AP_SHORT');
        $this->assertFalse($short['success']);
        $this->assertSame('AMOUNT_MISMATCH', $short['status']);

        $this->seed('AP_OK', 750);
        $gateway::$answer = ['pay_status' => 'Successful', 'amount' => '750.00', 'currency' => 'BDT'];
        $this->assertTrue($gateway->verify('AP_OK')['success']);
        $this->assertTrue($gateway->verify('AP_OK')['success']);
        $this->assertSame(1, $events, 'payment.success fires once');

        // A later failed check never undoes the payment
        $gateway::$answer = ['pay_status' => 'Failed'];
        $gateway->verify('AP_OK');
        $this->assertSame('COMPLETED', (string) DB::table('aamarpay_transactions')->where('tran_id', 'AP_OK')->first()->status);
        Event::forget('payment.success');
    }

    public function testControllerViews(): void
    {
        $controller = new AamarPayController();

        $checkoutHtml = $controller->checkout(new Request());
        $this->assertContains('Pay with AamarPay', $checkoutHtml);

        $_POST['tran_id'] = 'TXN_FAIL_123';
        $_POST['error_title'] = 'Call 01700000000 to get a refund';
        $failHtml = $controller->fail(new Request());
        $this->assertContains('Payment Failed', $failHtml);
        $this->assertContains('TXN_FAIL_123', $failHtml);
        $this->assertNotContains('01700000000', $failHtml, 'posted text is never shown back');

        $_POST['tran_id'] = 'TXN_CANCEL_123';
        $cancelHtml = $controller->cancel(new Request());
        $this->assertContains('Payment Cancelled', $cancelHtml);
        $this->assertContains('TXN_CANCEL_123', $cancelHtml);
        $_POST = [];
    }
}
