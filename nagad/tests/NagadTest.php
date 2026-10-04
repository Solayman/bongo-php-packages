<?php

namespace Bongo\Packages\Nagad\Tests;

use Bongo\Packages\Nagad\Controllers\NagadController;
use Bongo\Packages\Nagad\Nagad;
use InvalidArgumentException;
use RuntimeException;
use System\Http\Request;
use System\Testing\TestCase;

class NagadTest extends TestCase
{
    protected static array $testMerchantKeys;
    protected static array $testNagadKeys;

    public function setUp(): void
    {
        if (empty(self::$testMerchantKeys)) {
            // Generate test RSA keypairs once for the test run
            self::$testMerchantKeys = Nagad::generateKeyPair(1024);
            self::$testNagadKeys = Nagad::generateKeyPair(1024);
        }

        if (class_exists(\System\View\ViewEngine::class)) {
            \System\View\ViewEngine::addNamespace('nagad', dirname(__DIR__) . '/views');
        }
    }

    public function tearDown(): void
    {
        Nagad::setMockTransport(null);
    }

    public function testBaseUrlsForSandboxAndLive(): void
    {
        $sandbox = new Nagad([
            'merchant_id'          => 'test_merchant',
            'merchant_private_key' => self::$testMerchantKeys['private'],
            'nagad_public_key'     => self::$testNagadKeys['public'],
            'sandbox'              => true,
        ]);
        $this->assertSame(
            'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs',
            $sandbox->getBaseUrl()
        );

        $live = new Nagad([
            'merchant_id'          => 'live_merchant',
            'merchant_private_key' => self::$testMerchantKeys['private'],
            'nagad_public_key'     => self::$testNagadKeys['public'],
            'sandbox'              => false,
        ]);
        $this->assertSame(
            'https://api.mynagad.com/api/dfs',
            $live->getBaseUrl()
        );
    }

    public function testMissingCredentialsThrows(): void
    {
        $gateway = new Nagad([
            'merchant_id'          => '',
            'merchant_private_key' => '',
            'nagad_public_key'     => '',
        ]);

        $this->assertThrows(RuntimeException::class, function () use ($gateway) {
            $gateway->createPayment(['order_id' => 'ORD_1', 'amount' => 100]);
        });

        $this->assertThrows(RuntimeException::class, function () use ($gateway) {
            $gateway->verifyPayment('REF_123');
        });
    }

    public function testInvalidAmountThrows(): void
    {
        $gateway = new Nagad([
            'merchant_id'          => 'merchant_123',
            'merchant_private_key' => self::$testMerchantKeys['private'],
            'nagad_public_key'     => self::$testNagadKeys['public'],
        ]);

        $this->assertThrows(InvalidArgumentException::class, function () use ($gateway) {
            $gateway->createPayment(['order_id' => 'ORD_1', 'amount' => 0]);
        });

        $this->assertThrows(InvalidArgumentException::class, function () use ($gateway) {
            $gateway->createPayment(['order_id' => 'ORD_1', 'amount' => -50]);
        });
    }

    public function testRsaKeyGenerationAndEncryptionDecryptionRoundtrip(): void
    {
        $gateway = new Nagad([
            'merchant_id'          => 'merchant_123',
            'merchant_private_key' => self::$testMerchantKeys['private'],
            'nagad_public_key'     => self::$testMerchantKeys['public'], // Encrypt and decrypt with same keypair for test
        ]);

        $payload = [
            'merchantId' => 'merchant_123',
            'orderId'    => 'ORD_7788',
            'amount'     => '1500.00',
            'challenge'  => 'chall_1234567890abcdef',
        ];

        $encrypted = $gateway->encryptSensitiveData($payload);
        $this->assertTrue(!empty($encrypted));
        $this->assertNotSame(json_encode($payload), $encrypted);

        $decrypted = $gateway->decryptSensitiveData($encrypted);
        $this->assertSame($payload, $decrypted);
    }

    public function testSignatureGenerationAndVerification(): void
    {
        $gateway = new Nagad([
            'merchant_id'          => 'merchant_123',
            'merchant_private_key' => self::$testMerchantKeys['private'],
            'nagad_public_key'     => self::$testMerchantKeys['public'],
        ]);

        $message = "Test transaction payload for signing";
        $sig = $gateway->generateSignature($message);
        $this->assertTrue(!empty($sig));

        // Valid verification
        $this->assertTrue($gateway->verifySignature($message, $sig));

        // Tampered payload verification fails
        $this->assertFalse($gateway->verifySignature($message . " tampered", $sig));
    }

    public function testCreatePaymentWithMockTransport(): void
    {
        // Gateway encrypts with Nagad public key; Mock decrypts with Nagad private key
        // Mock returns response encrypted with Merchant public key; Gateway decrypts with Merchant private key
        Nagad::setMockTransport(function (string $url, string $method, array $data, array $headers) {
            if (str_contains($url, 'check-out/initialize')) {
                // Mock sensitive data response encrypted with Merchant public key
                $initRespData = [
                    'paymentReferenceId' => 'NAGAD_REF_998877',
                    'challenge'          => 'server_challenge_xyz',
                ];

                // Encrypt with Merchant public key so gateway can decrypt with its private key
                $json = json_encode($initRespData);
                $pubKey = openssl_pkey_get_public(self::$testMerchantKeys['public']);
                openssl_public_encrypt($json, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING);

                return [
                    'sensitiveData' => base64_encode($encrypted),
                    'signature'     => 'fake_sig',
                ];
            }

            if (str_contains($url, 'check-out/complete')) {
                return [
                    'status'      => 'Success',
                    'callBackUrl' => 'http://sandbox.mynagad.com:10080/portal/checkout?ref=NAGAD_REF_998877',
                    'message'     => 'Checkout completed successfully',
                ];
            }

            return [];
        });

        $gateway = new Nagad([
            'merchant_id'          => 'merchant_123',
            'merchant_private_key' => self::$testMerchantKeys['private'],
            'nagad_public_key'     => self::$testNagadKeys['public'],
            'sandbox'              => true,
        ]);

        $res = $gateway->createPayment([
            'order_id' => 'ORD_101',
            'amount'   => 1250.50,
        ]);

        $this->assertSame('Success', $res['status']);
        $this->assertSame('NAGAD_REF_998877', $res['payment_ref_id']);
        $this->assertSame('ORD_101', $res['order_id']);
        $this->assertContains('sandbox.mynagad.com', $res['callBackUrl']);
    }

    public function testVerifyPaymentWithMockTransport(): void
    {
        Nagad::setMockTransport(function (string $url, string $method, array $data, array $headers) {
            $this->assertContains('verify/payment/NAGAD_REF_998877', $url);
            return [
                'merchantId'          => 'merchant_123',
                'orderId'             => 'ORD_101',
                'paymentRefId'        => 'NAGAD_REF_998877',
                'amount'              => '1250.50',
                'clientMobileNo'      => '01711223344',
                'issuerTransactionId' => 'NAGAD_TXN_554433',
                'status'              => 'Success',
                'statusCode'          => '000',
            ];
        });

        $gateway = new Nagad([
            'merchant_id'          => 'merchant_123',
            'merchant_private_key' => self::$testMerchantKeys['private'],
            'nagad_public_key'     => self::$testNagadKeys['public'],
        ]);

        $verified = $gateway->verifyPayment('NAGAD_REF_998877');

        $this->assertTrue($verified['valid']);
        $this->assertSame('SUCCESS', $verified['status']);
        $this->assertSame('ORD_101', $verified['order_id']);
        $this->assertSame('NAGAD_TXN_554433', $verified['issuer_trx_id']);
        $this->assertSame('01711223344', $verified['client_mobile_no']);
    }

    public function testVerifyPaymentCancelledAndFailed(): void
    {
        $gateway = new Nagad([
            'merchant_id'          => 'merchant_123',
            'merchant_private_key' => self::$testMerchantKeys['private'],
            'nagad_public_key'     => self::$testNagadKeys['public'],
        ]);

        // Cancelled via callback query
        $cancelled = $gateway->verify([
            'payment_ref_id' => 'REF_CANCELLED',
            'status'         => 'cancel',
        ]);
        $this->assertFalse($cancelled['valid']);
        $this->assertSame('CANCELLED', $cancelled['status']);

        // Failed via verify endpoint
        Nagad::setMockTransport(function () {
            return [
                'status'     => 'Failed',
                'statusCode' => '101',
                'message'    => 'Insufficient balance in wallet',
            ];
        });

        $failed = $gateway->verifyPayment('REF_FAILED');
        $this->assertFalse($failed['valid']);
        $this->assertSame('FAILED', $failed['status']);
    }

    public function testControllerHandlesCallbackRedirect(): void
    {
        $controller = new NagadController();

        // Cancel callback returns cancel view
        $_GET['payment_ref_id'] = 'REF_1';
        $_GET['status'] = 'cancel';

        $viewOutput = $controller->callback(new Request());
        $this->assertContains('nagad-cancel-card', $viewOutput);

        // Aborted callback returns fail view
        $_GET['payment_ref_id'] = 'REF_2';
        $_GET['status'] = 'aborted';

        $viewOutput = $controller->callback(new Request());
        $this->assertContains('nagad-fail-card', $viewOutput);

        $_GET = [];
    }
}
