<?php

namespace Bongo\Packages\Sslcommerz\Tests;

use Bongo\Packages\Sslcommerz\SSLCommerz;
use RuntimeException;
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
}
