<?php

declare(strict_types=1);

namespace Bongo\Packages\Aamarpay\Tests;

use Bongo\Packages\Aamarpay\AamarPay;
use Bongo\Packages\Aamarpay\Controllers\AamarPayController;
use RuntimeException;
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

    public function testIsSuccessEvaluator(): void
    {
        $gateway = new AamarPay();

        $this->assertTrue($gateway->isSuccess(['pay_status' => 'Successful']));
        $this->assertTrue($gateway->isSuccess(['pay_status' => 'successful']));
        $this->assertFalse($gateway->isSuccess(['pay_status' => 'Failed']));
        $this->assertFalse($gateway->isSuccess(['pay_status' => 'Cancelled']));
        $this->assertFalse($gateway->isSuccess([]));
    }

    public function testControllerViews(): void
    {
        $controller = new AamarPayController();

        $checkoutHtml = $controller->checkout(new Request());
        $this->assertContains('Pay with AamarPay', $checkoutHtml);

        $_POST['tran_id'] = 'TXN_FAIL_123';
        $failHtml = $controller->fail(new Request());
        $this->assertContains('Payment Failed', $failHtml);
        $this->assertContains('TXN_FAIL_123', $failHtml);

        $_POST['tran_id'] = 'TXN_CANCEL_123';
        $cancelHtml = $controller->cancel(new Request());
        $this->assertContains('Payment Cancelled', $cancelHtml);
        $this->assertContains('TXN_CANCEL_123', $cancelHtml);
        $_POST = [];
    }
}
