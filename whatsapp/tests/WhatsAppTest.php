<?php

declare(strict_types=1);

namespace Bongo\Packages\WhatsApp\Tests;

use Bongo\Packages\WhatsApp\WhatsApp;
use System\Testing\TestCase;

class WhatsAppTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../src/helpers.php';
        WhatsApp::fake();
    }

    public function tearDown(): void
    {
        WhatsApp::clearSent();
        parent::tearDown();
    }

    public function testPhoneFormatting(): void
    {
        $wa = new WhatsApp('test-token', '123456789');
        $this->assertEquals('8801712345678', $wa->formatPhone('+880 1712-345678'));
        $this->assertEquals('15551234567', $wa->formatPhone('+1 (555) 123-4567'));
    }

    public function testSendTextInFakeMode(): void
    {
        $wa = new WhatsApp('test-token', '123456789');
        $res = $wa->sendText('01700000000', 'Hello world!', true);

        $this->assertEquals('whatsapp', $res['messaging_product']);
        $this->assertTrue(!empty($res['messages'][0]['id']));

        $sent = WhatsApp::getSent();
        $this->assertCount(1, $sent);
        $this->assertEquals('text', $sent[0]['payload']['type']);
        $this->assertEquals('Hello world!', $sent[0]['payload']['text']['body']);
        $this->assertTrue($sent[0]['payload']['text']['preview_url']);
    }

    public function testSendTemplate(): void
    {
        $wa = new WhatsApp('test-token', '123456789');
        $res = $wa->sendTemplate('01700000000', 'order_confirmation', 'en_US', [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '#12345']]],
        ]);

        $this->assertTrue(!empty($res['messages']));
        $sent = WhatsApp::getSent();
        $this->assertEquals('template', $sent[0]['payload']['type']);
        $this->assertEquals('order_confirmation', $sent[0]['payload']['template']['name']);
    }

    public function testSendOtp(): void
    {
        $wa = new WhatsApp('test-token', '123456789');
        $res = $wa->sendOtp('8801700000000', '891234');

        $this->assertTrue(!empty($res['messages']));
        $sent = WhatsApp::getSent();
        $this->assertEquals('template', $sent[0]['payload']['type']);
        $this->assertEquals('auth_otp', $sent[0]['payload']['template']['name']);
        $this->assertEquals('891234', $sent[0]['payload']['template']['components'][0]['parameters'][0]['text']);
    }

    public function testSendMedia(): void
    {
        $wa = new WhatsApp('test-token', '123456789');
        $res = $wa->sendMedia(
            '8801700000000',
            'document',
            'https://example.com/invoice.pdf',
            'Your monthly invoice',
            'invoice_october.pdf'
        );

        $this->assertTrue(!empty($res['messages']));
        $sent = WhatsApp::getSent();
        $this->assertEquals('document', $sent[0]['payload']['type']);
        $this->assertEquals('https://example.com/invoice.pdf', $sent[0]['payload']['document']['link']);
        $this->assertEquals('invoice_october.pdf', $sent[0]['payload']['document']['filename']);
    }

    public function testWebhookVerificationChallenge(): void
    {
        $wa = new WhatsApp('test-token', '123456789');

        $validQuery = [
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => 'my_secret_token',
            'hub_challenge'    => '1158201444',
        ];

        $challenge = $wa->verifyWebhook($validQuery, 'my_secret_token');
        $this->assertEquals('1158201444', $challenge);

        $invalidQuery = [
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge'    => 'CHALLENGE_STRING_123',
        ];

        $invalidChallenge = $wa->verifyWebhook($invalidQuery, 'my_secret_token');
        $this->assertNull($invalidChallenge);
    }

    public function testWebhookSignatureVerification(): void
    {
        $wa = new WhatsApp('test-token', '123456789');
        $secret = 'super_secret_app_key';
        $payload = json_encode(['event' => 'message_received']);

        $validHash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        $this->assertTrue($wa->verifySignature($payload, $validHash, $secret));

        $tamperedHash = 'sha256=' . hash_hmac('sha256', 'tampered', $secret);
        $this->assertFalse($wa->verifySignature($payload, $tamperedHash, $secret));
    }

    public function testGlobalHelper(): void
    {
        $client = whatsapp('helper-token', '999');
        $this->assertTrue($client instanceof WhatsApp);
    }

    public function testChallengeCannotCarryScriptAndEmptyTokenNeverMatches(): void
    {
        $wa = new WhatsApp('test-token', '123456789');
        $this->assertNull($wa->verifyWebhook(['hub_mode' => 'subscribe', 'hub_verify_token' => 'tok', 'hub_challenge' => '<script>alert(1)</script>'], 'tok'), 'only numeric challenges are echoed');
        $this->assertNull($wa->verifyWebhook(['hub_mode' => 'subscribe', 'hub_verify_token' => '', 'hub_challenge' => '123'], ''), 'an unset verify token never matches');
    }
}
