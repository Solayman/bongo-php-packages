<?php

declare(strict_types=1);

namespace Bongo\Packages\TwoFactor\Tests;

use Bongo\Packages\TwoFactor\Controllers\TwoFactorController;
use Bongo\Packages\TwoFactor\QrCode;
use Bongo\Packages\TwoFactor\TwoFactor;
use System\Http\Request;
use System\Testing\TestCase;

class TwoFactorTest extends TestCase
{
    public function setUp(): void
    {
        if (class_exists(\System\View\ViewEngine::class)) {
            \System\View\ViewEngine::addNamespace('two-factor', dirname(__DIR__) . '/views');
        }
    }

    public function testBase32EncodeDecodeRoundtrip(): void
    {
        $samples = [
            '',
            'f',
            'fo',
            'foo',
            'foob',
            'fooba',
            'foobar',
            'Hello World! 12345',
        ];

        foreach ($samples as $sample) {
            $encoded = TwoFactor::base32Encode($sample);
            $decoded = TwoFactor::base32Decode($encoded);
            $this->assertSame($sample, $decoded);
        }
    }

    public function testRfc6238TestVectors(): void
    {
        // RFC 6238 Test Secret: '12345678901234567890' in Base32
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', TwoFactor::generateTotp($secret, 59));
        $this->assertSame('081804', TwoFactor::generateTotp($secret, 1111111109));
        $this->assertSame('050471', TwoFactor::generateTotp($secret, 1111111111));
        $this->assertSame('005924', TwoFactor::generateTotp($secret, 1234567890));
        $this->assertSame('279037', TwoFactor::generateTotp($secret, 2000000000));
    }

    public function testSecretGeneration(): void
    {
        $secret16 = TwoFactor::generateSecret(16);
        $this->assertSame(16, strlen($secret16));
        $this->assertTrue(preg_match('/^[A-Z2-7]+$/', $secret16) === 1);

        $secret32 = TwoFactor::generateSecret(32);
        $this->assertSame(32, strlen($secret32));
    }

    public function testVerifyWithDriftTolerance(): void
    {
        $secret = TwoFactor::generateSecret(16);
        $now = 1700000000;

        // Current time code
        $codeNow = TwoFactor::generateTotp($secret, $now);
        $this->assertTrue(TwoFactor::verify($secret, $codeNow, 1, $now));

        // 30 seconds ago (window = 1 should pass)
        $codePast = TwoFactor::generateTotp($secret, $now - 30);
        $this->assertTrue(TwoFactor::verify($secret, $codePast, 1, $now));

        // 30 seconds future (window = 1 should pass)
        $codeFuture = TwoFactor::generateTotp($secret, $now + 30);
        $this->assertTrue(TwoFactor::verify($secret, $codeFuture, 1, $now));

        // 60 seconds ago (window = 1 should fail)
        $codeFarPast = TwoFactor::generateTotp($secret, $now - 60);
        $this->assertFalse(TwoFactor::verify($secret, $codeFarPast, 1, $now));
    }

    public function testVerifyRejectsInvalidCode(): void
    {
        $secret = TwoFactor::generateSecret(16);
        $this->assertFalse(TwoFactor::verify($secret, '12345')); // too short
        $this->assertFalse(TwoFactor::verify($secret, '1234567')); // too long
        $this->assertFalse(TwoFactor::verify($secret, 'abcdef')); // non-digits
        $this->assertFalse(TwoFactor::verify($secret, '000000')); // wrong code
    }

    public function testRecoveryCodesGenerationAndHashing(): void
    {
        $codes = TwoFactor::generateRecoveryCodes(8);
        $this->assertSame(8, count($codes));

        foreach ($codes as $code) {
            $this->assertSame(9, strlen($code)); // XXXX-XXXX
            $this->assertSame(1, preg_match('/^[A-F0-9]{4}-[A-F0-9]{4}$/', $code));

            $hash = TwoFactor::hashRecoveryCode($code);
            $this->assertSame(64, strlen($hash)); // SHA-256
        }
    }

    public function testRecoveryCodeVerificationAndConsumption(): void
    {
        $codes = TwoFactor::generateRecoveryCodes(4);
        $hashed = array_map([TwoFactor::class, 'hashRecoveryCode'], $codes);

        $targetCode = $codes[1];

        // First verification succeeds and consumes the code
        $valid = TwoFactor::verifyRecoveryCode($targetCode, $hashed);
        $this->assertTrue($valid);
        $this->assertSame(3, count($hashed));

        // Second verification with the same code fails (single-use)
        $secondAttempt = TwoFactor::verifyRecoveryCode($targetCode, $hashed);
        $this->assertFalse($secondAttempt);

        // Verification with invalid code fails
        $invalidAttempt = TwoFactor::verifyRecoveryCode('INVALID-CODE', $hashed);
        $this->assertFalse($invalidAttempt);
        $this->assertSame(3, count($hashed));
    }

    public function testQrCodeUrlAndSvgGeneration(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $url = TwoFactor::qrCodeUrl('john@example.com', $secret, 'BongoApp');

        $this->assertContains('otpauth://totp/BongoApp:john%40example.com', $url);
        $this->assertContains('secret=JBSWY3DPEHPK3PXP', $url);
        $this->assertContains('issuer=BongoApp', $url);

        $svg = TwoFactor::qrCodeSvg('john@example.com', $secret, 'BongoApp', 200);
        $this->assertContains('<svg', $svg);
        $this->assertContains('xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertContains('<rect', $svg);

        $dataUri = TwoFactor::qrCodeDataUri('john@example.com', $secret, 'BongoApp');
        $this->assertContains('data:image/svg+xml;base64,', $dataUri);
    }

    public function testControllerSetupAndChallengeViews(): void
    {
        $controller = new TwoFactorController();

        $setupHtml = $controller->setup();
        $this->assertContains('two-factor-card', $setupHtml);
        $this->assertContains('two-factor-secret-box', $setupHtml);

        $challengeHtml = $controller->challenge();
        $this->assertContains('two-factor-card', $challengeHtml);
        $this->assertContains('id="totp-form"', $challengeHtml);
        $this->assertContains('id="recovery-form"', $challengeHtml);
    }
}
