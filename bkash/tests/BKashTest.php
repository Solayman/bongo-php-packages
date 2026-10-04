<?php

namespace Bongo\Packages\Bkash\Tests;

use Bongo\Packages\Bkash\BKash;
use InvalidArgumentException;
use RuntimeException;
use System\Testing\TestCase;

class BKashTest extends TestCase
{
    protected function tearDown(): void
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

    public function testCallbackVerificationSuccessAndFailure(): void
    {
        BKash::setMockTransport(function ($url, $method, $data, $headers) {
            if (str_contains($url, '/token/grant')) {
                return ['id_token' => 'token_mock', 'expires_in' => 3600];
            }
            if (str_contains($url, '/checkout/execute')) {
                return [
                    'statusCode'        => '0000',
                    'transactionStatus' => 'Completed',
                    'paymentID'         => $data['paymentID'],
                    'trxID'             => 'TRX_SUCCESS_99',
                    'amount'            => '350.00',
                    'currency'          => 'BDT',
                    'customerMsisdn'    => '01700000000',
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

        // Success flow
        $verified = $gateway->verify([
            'paymentID' => 'PAY_1001',
            'status'    => 'success',
        ]);
        $this->assertTrue($verified['valid']);
        $this->assertSame('COMPLETED', $verified['status']);
        $this->assertSame('TRX_SUCCESS_99', $verified['trx_id']);

        // Cancel flow (no server execute call needed)
        $cancelled = $gateway->verify([
            'paymentID' => 'PAY_1001',
            'status'    => 'cancel',
        ]);
        $this->assertFalse($cancelled['valid']);
        $this->assertSame('CANCELLED', $cancelled['status']);

        // Failure flow
        $failed = $gateway->verify([
            'paymentID' => 'PAY_1001',
            'status'    => 'failure',
        ]);
        $this->assertFalse($failed['valid']);
        $this->assertSame('FAILED', $failed['status']);
    }
}
