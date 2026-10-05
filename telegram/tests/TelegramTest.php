<?php

declare(strict_types=1);

namespace Bongo\Packages\Telegram\Tests;

use Bongo\Packages\Telegram\Telegram;
use InvalidArgumentException;
use RuntimeException;
use System\Testing\TestCase;

class TelegramTest extends TestCase
{
    public function setUp(): void
    {
        Telegram::clearFake();
    }

    public function tearDown(): void
    {
        Telegram::clearFake();
    }

    public function testMissingTokenThrowsException(): void
    {
        $this->assertThrows(RuntimeException::class, function () {
            $tg = new Telegram(['bot_token' => '', 'chat_id' => '12345']);
            $tg->sendMessage('12345', 'Hello');
        });
    }

    public function testMissingChatIdThrowsException(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function () {
            $tg = new Telegram(['bot_token' => 'dummy_token', 'chat_id' => '']);
            $tg->sendMessage(null, 'Hello');
        });
    }

    public function testSendMessageWithFake(): void
    {
        Telegram::fake([
            'ok'     => true,
            'result' => [
                'message_id' => 777,
                'text'       => 'Test message',
            ],
        ]);

        $tg = new Telegram([
            'bot_token' => '123:dummy',
            'chat_id'   => '987654',
        ]);

        $res = $tg->sendMessage('987654', 'Test message');
        $this->assertTrue($res['ok']);
        $this->assertSame(777, $res['result']['message_id']);
    }

    public function testNotifyUsesDefaultChatId(): void
    {
        Telegram::fake();

        $tg = new Telegram([
            'bot_token' => '123:dummy',
            'chat_id'   => '987654',
        ]);

        $res = $tg->notify('Server started');
        $this->assertTrue($res['ok']);
    }

    public function testAlertFormatting(): void
    {
        Telegram::fake();

        $tg = new Telegram([
            'bot_token' => '123:dummy',
            'chat_id'   => '987654',
        ]);

        $res = $tg->alert('Database Connection Failed', 'SQLSTATE[HY000] connection timeout', 'critical', [
            'host' => '127.0.0.1',
            'port' => 3306,
        ]);

        $this->assertTrue($res['ok']);
    }

    public function testGlobalHelpers(): void
    {
        require_once dirname(__DIR__) . '/src/Helpers.php';

        Telegram::fake();

        $_ENV['TELEGRAM_BOT_TOKEN'] = 'test_token';
        $_ENV['TELEGRAM_CHAT_ID'] = 'test_chat';

        $res = telegram_notify('Notification test');
        $this->assertTrue($res['ok']);

        $resAlert = telegram_alert('Test Title', 'Test alert message', 'warning');
        $this->assertTrue($resAlert['ok']);
    }
}
