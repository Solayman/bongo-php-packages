<?php

namespace Bongo\Packages\Webhooks\Tests;

use Bongo\Packages\Webhooks\Webhook;
use System\Testing\TestCase;

class WebhooksTest extends TestCase
{
    public function testSignatureGenerationAndVerification(): void
    {
        $payload = ['order_id' => 123, 'total' => 450.00];
        $secret = 'super_secret_signing_key';
        $timestamp = 1700000000;

        $header = Webhook::sign($payload, $secret, $timestamp);
        $this->assertTrue(str_starts_with($header, 't=1700000000,v1='));

        // Verify with matching tolerance
        $this->assertTrue(Webhook::verify($payload, $header, $secret, tolerance: 0));

        // Verify fails with wrong secret
        $this->assertFalse(Webhook::verify($payload, $header, 'wrong_key', tolerance: 0));

        // Verify fails with altered payload
        $altered = ['order_id' => 123, 'total' => 999.00];
        $this->assertFalse(Webhook::verify($altered, $header, $secret, tolerance: 0));
    }

    public function testToleranceExceededFailsVerification(): void
    {
        $payload = ['foo' => 'bar'];
        $secret = 'test_secret';
        $oldTimestamp = time() - 600; // 10 minutes ago

        $header = Webhook::sign($payload, $secret, $oldTimestamp);

        // Standard tolerance 300s (5 mins) should reject 10 min old signature
        $this->assertFalse(Webhook::verify($payload, $header, $secret, tolerance: 300));
    }

    public function testWebhookFakeAndDispatch(): void
    {
        Webhook::fake();
        $this->assertTrue(Webhook::isFaking());

        $payload = ['event' => 'user.created', 'user_id' => 42];
        $res = Webhook::dispatch('https://example.com/webhook', 'user.created', $payload, 'sec_123');

        $this->assertTrue($res['success']);
        $this->assertSame(200, $res['status_code']);

        $dispatched = Webhook::getDispatched();
        $this->assertCount(1, $dispatched);
        $this->assertSame('https://example.com/webhook', $dispatched[0]['url']);
        $this->assertSame('user.created', $dispatched[0]['event']);
        $this->assertSame($payload, $dispatched[0]['payload']);
        $this->assertTrue(!empty($dispatched[0]['headers']['X-Bongo-Signature']));
    }
}
