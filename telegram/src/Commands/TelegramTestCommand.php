<?php

declare(strict_types=1);

namespace Bongo\Packages\Telegram\Commands;

use Bongo\Packages\Telegram\Telegram;
use System\Console\Command;
use Throwable;

class TelegramTestCommand extends Command
{
    protected string $name = 'telegram:test';
    protected string $description = 'Verify Telegram Bot Token and send a test ping';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking Telegram Bot configuration...");

        $token = (string) env('TELEGRAM_BOT_TOKEN', '');
        $chatId = (string) env('TELEGRAM_CHAT_ID', '');

        if ($token === '') {
            $this->error("Missing TELEGRAM_BOT_TOKEN in .env");
            return 1;
        }

        $this->line("  Bot Token: " . substr($token, 0, 6) . '****' . substr($token, -4));
        $this->line("  Target Chat: " . ($chatId !== '' ? $chatId : 'None specified (will check getMe only)'));

        try {
            $telegram = new Telegram();
            $this->line("Validating bot identity with Telegram API...");
            $me = $telegram->getMe();

            if (!empty($me['ok'])) {
                $botUser = $me['result']['username'] ?? 'Unknown';
                $botName = $me['result']['first_name'] ?? 'Bot';
                $this->info("Bot authenticated: {$botName} (@{$botUser})");

                if ($chatId !== '') {
                    $this->line("Sending ping message to chat {$chatId}...");
                    $telegram->alert('Connectivity Test', 'This is a test notification from Bongo PHP.', 'success');
                    $this->info("Ping message delivered successfully!");
                }

                return 0;
            }

            $this->error("Telegram authentication failed.");
            return 1;
        } catch (Throwable $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}
