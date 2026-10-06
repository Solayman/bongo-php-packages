<?php

namespace Bongo\Packages\Sms\Tests;

use Bongo\Packages\Sms\Contracts\SMSDriver;
use Bongo\Packages\Sms\Drivers\AlphaSMSDriver;
use Bongo\Packages\Sms\Drivers\BulkSMSBDDriver;
use Bongo\Packages\Sms\Drivers\LogDriver;
use Bongo\Packages\Sms\Drivers\SSLWirelessDriver;
use Bongo\Packages\Sms\OTP;
use Bongo\Packages\Sms\SMS;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

class SMSTest extends TestCase
{
    public function setUp(): void
    {
        if (class_exists(Schema::class)) {
            Schema::dropIfExists('otp_codes');
            Schema::create('otp_codes', function (Blueprint $table) {
                $table->id();
                $table->string('phone', 30)->index();
                $table->string('code_hash', 255);
                $table->string('purpose', 50)->default('login');
                $table->integer('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function tearDown(): void
    {
        if (class_exists(Schema::class)) {
            Schema::dropIfExists('otp_codes');
        }
        LogDriver::clear();
        BulkSMSBDDriver::setMockTransport(null);
        SSLWirelessDriver::setMockTransport(null);
        AlphaSMSDriver::setMockTransport(null);
        SMS::reset();
    }

    public function testLogDriverRecordsMessage(): void
    {
        $driver = new LogDriver(['file' => 'storage/logs/sms_test.log']);
        $result = $driver->send('01700000000', 'Test message');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['id']);

        $sent = LogDriver::sent();
        $this->assertCount(1, $sent);
        $this->assertSame('01700000000', $sent[0]['to']);
        $this->assertSame('Test message', $sent[0]['message']);
    }

    public function testSMSManagerDefaultDriver(): void
    {
        $driver = SMS::driver('log');
        $this->assertTrue($driver instanceof LogDriver);

        $result = SMS::send('01711111111', 'Hello Bongo SMS', 'log');
        $this->assertTrue($result['success']);

        $sent = LogDriver::sent();
        $this->assertCount(1, $sent);
        $this->assertSame('01711111111', $sent[0]['to']);
    }

    public function testCustomDriverExtension(): void
    {
        SMS::extend('dummy', function () {
            return new class implements SMSDriver {
                public function send(string $to, string $message): array
                {
                    return ['success' => true, 'id' => 'DUMMY_42', 'raw' => [], 'error' => null];
                }
            };
        });

        $res = SMS::send('01800000000', 'Dummy test', 'dummy');
        $this->assertTrue($res['success']);
        $this->assertSame('DUMMY_42', $res['id']);
    }

    public function testBulkSMSBDDriverWithMock(): void
    {
        BulkSMSBDDriver::setMockTransport(function ($url, $params) {
            return [
                'response_code'  => 202,
                'message_id'     => 'BULK_991',
                'success_message' => 'SMS Submitted Successfully',
            ];
        });

        $driver = new BulkSMSBDDriver([
            'api_key'   => 'test_key',
            'sender_id' => 'test_sid',
        ]);

        $res = $driver->send('01700000000', 'Hello from BulkSMSBD');
        $this->assertTrue($res['success']);
        $this->assertSame('BULK_991', $res['id']);
    }

    public function testSSLWirelessDriverWithMock(): void
    {
        SSLWirelessDriver::setMockTransport(function ($url, $payload) {
            return [
                'status'      => 'SUCCESS',
                'status_code' => 200,
                'smsinfo'     => [
                    ['sms_id' => 'SSL_772'],
                ],
            ];
        });

        $driver = new SSLWirelessDriver([
            'api_token' => 'test_token',
            'sid'       => 'test_sid',
        ]);

        $res = $driver->send('01700000000', 'Hello from SSL Wireless');
        $this->assertTrue($res['success']);
        $this->assertSame('SSL_772', $res['id']);
    }

    public function testAlphaSMSDriverWithMock(): void
    {
        AlphaSMSDriver::setMockTransport(function ($url, $payload) {
            return [
                'error' => 0,
                'msg'   => 'Success',
                'data'  => ['request_id' => 'ALPHA_330'],
            ];
        });

        $driver = new AlphaSMSDriver([
            'api_key'   => 'test_key',
            'sender_id' => 'test_sid',
        ]);

        $res = $driver->send('01700000000', 'Hello from Alpha SMS');
        $this->assertTrue($res['success']);
        $this->assertSame('ALPHA_330', $res['id']);
    }

    public function testOTPCodeGenerationAndNormalization(): void
    {
        $code4 = OTP::generateCode(4);
        $this->assertSame(4, strlen($code4));
        $this->assertTrue(ctype_digit($code4));

        $code6 = OTP::generateCode(6);
        $this->assertSame(6, strlen($code6));
        $this->assertTrue(ctype_digit($code6));

        $this->assertSame('01700000000', OTP::normalizePhone('8801700000000'));
        $this->assertSame('01700000000', OTP::normalizePhone('+8801700000000'));
        $this->assertSame('01700000000', OTP::normalizePhone('01700000000'));
    }

    public function testOTPSendAndVerifyFlow(): void
    {
        $phone = '01799999999';
        $purpose = 'test_login_' . bin2hex(random_bytes(3));

        $res = OTP::send($phone, $purpose, 'log');
        $this->assertTrue($res['success']);

        $sent = LogDriver::sent();
        $this->assertCount(1, $sent);
        $this->assertSame($phone, $sent[0]['to']);

        // Extract 6-digit code from the sent SMS message
        preg_match('/(\d{6})/', $sent[0]['message'], $matches);
        $this->assertTrue(isset($matches[1]), 'OTP code was not found in sent SMS');
        $code = $matches[1];

        // 1. Wrong code must fail
        $wrong = OTP::verify($phone, '000000', $purpose);
        $this->assertFalse($wrong);

        // 2. Correct code must succeed
        $correct = OTP::verify($phone, $code, $purpose);
        $this->assertTrue($correct);

        // 3. Reusing the same code must fail (single-use)
        $reuse = OTP::verify($phone, $code, $purpose);
        $this->assertFalse($reuse);
    }

    public function testGuessesStopAtMaxAttemptsEvenForTheRightCode(): void
    {
        $phone = '01788888888';
        $purpose = 'test_limit_' . bin2hex(random_bytes(3));
        OTP::send($phone, $purpose, 'log');
        $messages = LogDriver::sent();
        preg_match('/(\d{6})/', end($messages)['message'] ?? '', $matches);
        $code = $matches[1] ?? '';
        $wrong = $code === '111111' ? '222222' : '111111';

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse(OTP::verify($phone, $wrong, $purpose));
        }
        $this->assertFalse(OTP::verify($phone, $code, $purpose), 'after 5 wrong guesses the code is dead');
    }

    public function testOtpRoutesUseSessionAndRateLimits(): void
    {
        $router = new \System\Routing\Router();
        $router->loadFile(dirname(__DIR__) . '/routes.php');
        foreach ($router->routes() as $route) {
            $this->assertTrue(in_array('web', $route->middleware, true), "{$route->path} needs the session and CSRF");
            if (in_array('POST', $route->methods, true)) {
                $limited = array_filter($route->middleware, fn($m) => str_starts_with((string) $m, 'throttle:'));
                $this->assertTrue($limited !== [], "POST {$route->path} must be rate limited");
            }
        }
    }
}
