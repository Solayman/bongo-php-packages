<?php

declare(strict_types=1);

namespace Bongo\Packages\Honeypot\Tests;

use Bongo\Packages\Honeypot\Honeypot;
use Bongo\Packages\Honeypot\Exceptions\SpamDetectedException;
use System\Testing\TestCase;

class HoneypotTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/src/helpers.php';
        Honeypot::unfake();
    }

    public function testRenderOutputsDecoyAndSignedTimestamp(): void
    {
        $html = honeypot()->render();

        $this->assertTrue(str_contains($html, '<input type="text" name="my_name"'));
        $this->assertTrue(str_contains($html, '<input type="hidden" name="_hp_time"'));
        $this->assertTrue(str_contains($html, 'display:none'));
        $this->assertSame($html, (string) honeypot());
    }

    public function testValidSubmissionAfterDelayPasses(): void
    {
        $hp = Honeypot::instance();
        // Simulate form rendered 5 seconds ago
        $token = $hp->generateToken(time() - 5);

        $data = [
            'my_name' => '',
            '_hp_time' => $token,
            'email' => 'user@example.com',
        ];

        $res = $hp->validate($data);
        $this->assertTrue($res->isValid());
        $this->assertFalse($res->isSpam());
        $this->assertSame(5, $res->elapsedSeconds());
    }

    public function testDecoyFilledFlaggedAsSpam(): void
    {
        $hp = Honeypot::instance();
        $token = $hp->generateToken(time() - 5);

        $data = [
            'my_name' => 'Spam Bot Name',
            '_hp_time' => $token,
        ];

        $res = $hp->validate($data);
        $this->assertFalse($res->isValid());
        $this->assertTrue($res->isSpam());
        $this->assertSame('decoy_filled', $res->reason());
    }

    public function testSubmittedTooFastFlaggedAsSpam(): void
    {
        $hp = Honeypot::instance();
        // Submitted immediately (0 seconds)
        $token = $hp->generateToken(time());

        $data = [
            'my_name' => '',
            '_hp_time' => $token,
        ];

        $res = $hp->validate($data);
        $this->assertFalse($res->isValid());
        $this->assertSame('too_fast', $res->reason());
    }

    public function testInvalidSignatureFlaggedAsSpam(): void
    {
        $hp = Honeypot::instance();

        $data = [
            'my_name' => '',
            '_hp_time' => 'tampered_fake_token',
        ];

        $res = $hp->validate($data);
        $this->assertFalse($res->isValid());
        $this->assertSame('invalid_signature', $res->reason());
    }

    public function testGuardThrowsSpamDetectedException(): void
    {
        $this->assertThrows(SpamDetectedException::class, function () {
            Honeypot::guard(['my_name' => 'bot']);
        });
    }

    public function testFakeBypassesValidation(): void
    {
        Honeypot::fake();

        // Even with bot data, fake passes
        $res = Honeypot::check(['my_name' => 'bot', '_hp_time' => 'bad']);
        $this->assertTrue($res->isValid());
    }
}
