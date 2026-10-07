<?php

declare(strict_types=1);

namespace Bongo\Packages\PushNotification\Tests;

use Bongo\Packages\PushNotification\Push;
use Bongo\Packages\PushNotification\WebPushCrypto;
use RuntimeException;
use System\Testing\TestCase;

class PushNotificationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../src/helpers.php';
        Push::fake();
    }

    public function tearDown(): void
    {
        Push::clearSent();
        parent::tearDown();
    }

    public function testSendWebPushInFakeMode(): void
    {
        $push = new Push([
            'webpush' => [
                'subject'    => 'mailto:test@example.com',
                'public_key' => 'mock-pub-key',
                'ttl'        => 3600,
            ],
        ]);

        $sub = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-sub-token',
            'keys'     => ['p256dh' => 'key1', 'auth' => 'key2'],
        ];

        $res = $push->sendWebPush($sub, [
            'title' => 'Order Delivered',
            'body'  => 'Your order #99281 has arrived.',
            'url'   => 'https://example.com/orders/99281',
        ]);

        $this->assertEquals(201, $res['status']);
        $sent = Push::getSent();
        $this->assertCount(1, $sent);
        $this->assertEquals('webpush', $sent[0]['driver']);
        $this->assertEquals('Order Delivered', $sent[0]['payload']['title']);
    }

    public function testSendFcmInFakeMode(): void
    {
        $push = new Push();
        $res = $push->sendFcm('device-token-12345', [
            'title' => 'Flash Sale',
            'body'  => '50% off everything today only!',
        ], ['promo_id' => 'sale2026']);

        $this->assertEquals(200, $res['status']);
        $this->assertTrue(strpos($res['message_id'], 'projects/fcm/messages/') !== false);

        $sent = Push::getSent();
        $this->assertCount(1, $sent);
        $this->assertEquals('fcm', $sent[0]['driver']);
        $this->assertEquals('device-token-12345', $sent[0]['device_token']);
    }

    public function testEmptyWebPushEndpointThrowsException(): void
    {
        $push = new Push();
        $caught = false;

        try {
            $push->sendWebPush('', ['title' => 'Fail']);
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertTrue(strpos($e->getMessage(), 'endpoint is required') !== false);
        }

        $this->assertTrue($caught);
    }

    public function testEmptyFcmTokenThrowsException(): void
    {
        $push = new Push();
        $caught = false;

        try {
            $push->sendFcm('', ['title' => 'Fail']);
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertTrue(strpos($e->getMessage(), 'token is required') !== false);
        }

        $this->assertTrue($caught);
    }

    public function testVapidHeadersAreValidEs256Signatures(): void
    {
        $keys = Push::generateVapidKeys();
        $push = new Push(['webpush' => ['subject' => 'mailto:ops@example.com'] + $keys]);

        $header = $push->generateVapidAuthorization('https://fcm.googleapis.com')['Authorization'];
        $this->assertTrue(preg_match('/^vapid t=([^.]+)\.([^.]+)\.([^,]+), k=(.+)$/', $header, $m) === 1);
        $this->assertSame($keys['public_key'], $m[4]);
        $this->assertSame('https://fcm.googleapis.com', json_decode(WebPushCrypto::base64UrlDecode($m[2]), true)['aud']);

        // The signature verifies against the public key (raw r||s -> DER for openssl_verify)
        $raw = WebPushCrypto::base64UrlDecode($m[3]);
        $int = function (string $v): string {
            $v = ltrim($v, "\x00");
            if ($v === '' || ord($v[0]) > 0x7f) {
                $v = "\x00" . $v;
            }
            return "\x02" . chr(strlen($v)) . $v;
        };
        $seq = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
        $der = "\x30" . chr(strlen($seq)) . $seq;
        $point = WebPushCrypto::base64UrlDecode($keys['public_key']);
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $this->assertSame(1, openssl_verify($m[1] . '.' . $m[2], $der, $pem, OPENSSL_ALGO_SHA256));
    }

    public function testPayloadEncryptionMatchesRfc8291(): void
    {
        $d = fn(string $s) => WebPushCrypto::base64UrlDecode($s);
        $body = WebPushCrypto::encrypt(
            'When I grow up, I want to be a watermelon',
            $d('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
            $d('BTBZMqHH6r4Tts7J_aSIgg'),
            ['public' => $d('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8'), 'private' => $d('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw')],
            $d('DGv6ra1nlYgDCS1FRnbzlw')
        );
        $this->assertSame(
            'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
            WebPushCrypto::base64UrlEncode($body),
            'RFC 8291 appendix A test vector'
        );
    }

    public function testBrowserSuppliedEndpointsCannotReachInternalAddresses(): void
    {
        foreach (['http://fcm.googleapis.com/x', 'https://127.0.0.1/x', 'https://169.254.169.254/latest', 'https://10.0.0.8/x'] as $endpoint) {
            $this->assertThrows(RuntimeException::class, fn() => Push::assertPushEndpoint($endpoint), $endpoint);
        }
    }

    public function testGlobalHelper(): void
    {
        $p = push();
        $this->assertTrue($p instanceof Push);
    }
}
