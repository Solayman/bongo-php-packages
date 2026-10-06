<?php

declare(strict_types=1);

namespace Bongo\Packages\Captcha\Tests;

use Bongo\Packages\Captcha\Captcha;
use Bongo\Packages\Captcha\Drivers\HcaptchaDriver;
use Bongo\Packages\Captcha\Drivers\ImageDriver;
use Bongo\Packages\Captcha\Drivers\MathDriver;
use Bongo\Packages\Captcha\Drivers\RecaptchaDriver;
use Bongo\Packages\Captcha\Drivers\TurnstileDriver;
use System\Testing\TestCase;

class CaptchaTest extends TestCase
{
    public function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION = [];
        $_POST = [];
        Captcha::reset();
    }

    public function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        Captcha::reset();
    }

    public function testMathDriverLifecycle(): void
    {
        $driver = new MathDriver([
            'session_key' => 'test_captcha_math',
            'min'         => 5,
            'max'         => 10,
            'operators'   => ['+'],
        ]);

        $this->assertSame('test_captcha_math', $driver->inputName());
        $this->assertSame('', $driver->script());

        // Generate problem
        $problem = $driver->generate();
        $this->assertContains('+', $problem['question']);
        $this->assertTrue(!empty($problem['answer']));
        $this->assertArrayHasKey('test_captcha_math', $_SESSION);

        // Verify wrong answer fails
        $this->assertFalse($driver->verify('99999'));

        // Regenerate for valid test
        $problem = $driver->generate();
        $correctAnswer = (string) $problem['answer'];

        // Verify correct answer succeeds
        $this->assertTrue($driver->verify($correctAnswer));

        // Replay attack protection (second call must fail because session was cleared)
        $this->assertFalse($driver->verify($correctAnswer));
    }

    public function testMathDriverRender(): void
    {
        $driver = new MathDriver(['session_key' => 'math_test']);
        $html = $driver->render(['id' => 'custom_math_id']);

        $this->assertContains('name="math_test"', $html);
        $this->assertContains('id="custom_math_id"', $html);
        $this->assertContains('Security Question:', $html);
    }

    public function testImageDriverLifecycle(): void
    {
        $driver = new ImageDriver([
            'session_key' => 'test_captcha_img',
            'length'      => 4,
            'characters'  => 'ABCDEF123456',
        ]);

        $this->assertSame('test_captcha_img', $driver->inputName());
        $phrase = $driver->generatePhrase();
        $this->assertSame(4, strlen($phrase));

        // Wrong input fails
        $this->assertFalse($driver->verify('XXXX'));

        // Regenerate for success test
        $phrase = $driver->generatePhrase();
        // Case-insensitivity verification
        $this->assertTrue($driver->verify(strtolower($phrase)));

        // Replay prevention
        $this->assertFalse($driver->verify($phrase));
    }

    public function testImageDriverRenderProducesBase64(): void
    {
        $driver = new ImageDriver(['session_key' => 'img_test']);
        $html = $driver->render(['class' => 'my-custom-img-captcha']);

        $this->assertContains('data:image/', $html);
        $this->assertContains('name="img_test"', $html);
        $this->assertContains('class="my-custom-img-captcha"', $html);
    }

    public function testTurnstileDriverRenderAndInput(): void
    {
        $driver = new TurnstileDriver([
            'site_key' => '0x4AAAAAAABBBBBBBB',
            'theme'    => 'dark',
        ]);

        $this->assertSame('cf-turnstile-response', $driver->inputName());
        $this->assertContains('challenges.cloudflare.com/turnstile', $driver->script());

        $html = $driver->render(['class' => 'turnstile-box']);
        $this->assertContains('class="cf-turnstile turnstile-box"', $html);
        $this->assertContains('data-sitekey="0x4AAAAAAABBBBBBBB"', $html);
        $this->assertContains('data-theme="dark"', $html);
    }

    public function testTurnstileDriverEmptyResponseFails(): void
    {
        $driver = new TurnstileDriver([
            'site_key'   => 'site_key',
            'secret_key' => 'secret_key',
        ]);

        $this->assertFalse($driver->verify(''));
    }

    public function testRecaptchaDriverRenderAndInput(): void
    {
        $driver = new RecaptchaDriver([
            'site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
            'version'  => 'v2',
        ]);

        $this->assertSame('g-recaptcha-response', $driver->inputName());
        $this->assertContains('google.com/recaptcha/api.js', $driver->script());

        $html = $driver->render();
        $this->assertContains('class="g-recaptcha"', $html);
        $this->assertContains('data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"', $html);
    }

    public function testHcaptchaDriverRenderAndInput(): void
    {
        $driver = new HcaptchaDriver([
            'site_key' => '10000000-ffff-ffff-ffff-000000000001',
        ]);

        $this->assertSame('h-captcha-response', $driver->inputName());
        $this->assertContains('hcaptcha.com/1/api.js', $driver->script());

        $html = $driver->render();
        $this->assertContains('class="h-captcha"', $html);
        $this->assertContains('data-sitekey="10000000-ffff-ffff-ffff-000000000001"', $html);
    }

    public function testCaptchaFacadeAndHelpers(): void
    {
        require_once dirname(__DIR__) . '/src/Helpers.php';

        Captcha::setConfig([
            'driver' => 'math',
            'math'   => [
                'session_key' => 'facade_math',
                'min'         => 1,
                'max'         => 5,
                'operators'   => ['+'],
            ],
        ]);

        $driver = Captcha::driver();
        $this->assertTrue($driver instanceof MathDriver);

        $html = captcha_render(['id' => 'facade_id']);
        $this->assertContains('id="facade_id"', $html);

        $stored = $_SESSION['facade_math'] ?? null;
        $this->assertNotNull($stored);

        $this->assertTrue(captcha_verify($stored['answer']));
        $this->assertFalse(captcha_verify($stored['answer'])); // consumed
    }

    public function testMiddlewareLetsAValidAnswerThroughAndRejectsOthers(): void
    {
        Captcha::setConfig(['driver' => 'math', 'math' => ['session_key' => 'mw_math', 'min' => 1, 'max' => 5, 'operators' => ['+']]]);
        Captcha::driver()->render();
        $answer = $_SESSION['mw_math']['answer'];
        $middleware = new \Bongo\Packages\Captcha\Middleware\VerifyCaptcha();

        $_POST = ['mw_math' => (string) $answer];
        $middleware->handle();   // valid: returns normally
        $this->assertTrue(true);

        // A wrong (or replayed) answer from an API client is a 422
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        try {
            $this->assertThrows(\System\Http\HttpException::class, fn() => $middleware->handle());
        } finally {
            unset($_SERVER['HTTP_ACCEPT']);
            $_POST = [];
        }
    }

    public function testTemplateDirectivesAreRegistered(): void
    {
        require_once dirname(__DIR__) . '/bootstrap.php';
        $php = (new \System\View\ViewCompiler())->compile("@captcha @captchascript");
        $this->assertContains('Captcha::render([])', $php);
        $this->assertContains('Captcha::script(null)', $php);
    }
}
