<?php

declare(strict_types=1);

namespace Bongo\Packages\PushNotification\Tests;

use Bongo\Packages\PushNotification\Push;
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

    public function testVapidHeadersGeneration(): void
    {
        $push = new Push([
            'webpush' => [
                'subject'    => 'mailto:ops@example.com',
                'public_key' => 'BLvI...public_key',
            ],
        ]);

        $headers = $push->generateVapidAuthorization('https://fcm.googleapis.com');
        $this->assertTrue(isset($headers['Authorization']));
        $this->assertTrue(strpos($headers['Authorization'], 'vapid t=') !== false);
    }

    public function testGlobalHelper(): void
    {
        $p = push();
        $this->assertTrue($p instanceof Push);
    }
}
