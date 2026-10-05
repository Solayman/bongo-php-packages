<?php

declare(strict_types=1);

namespace Bongo\Packages\Telegram\Commands;

use Bongo\Packages\Telegram\Telegram;
use System\Console\Command;
use Throwable;

class TelegramSendCommand extends Command
{
    protected string $name = 'telegram:send';
    protected string $description = 'Dispatch a message to Telegram';

    public function handle(array $args, array $options): int
    {
        $message = $args[0] ?? null;
        if (empty($message)) {
            $this->error("Usage: php bongo telegram:send \"Your message text here\" [--chat=id]");
            return 1;
        }

        $chatId = $options['chat'] ?? $options['chat_id'] ?? null;

        try {
            $telegram = new Telegram();
            $this->line("Dispatching message to Telegram...");
            $res = $telegram->sendMessage($chatId, $message);

            if (!empty($res['ok'])) {
                $this->info("Message sent successfully! Message ID: " . ($res['result']['message_id'] ?? 'N/A'));
                return 0;
            } else {
                $this->error("Failed to send message: " . ($res['description'] ?? 'Unknown error'));
                return 1;
            }
        } catch (Throwable $e) {
            $this->error("Telegram Error: " . $e->getMessage());
            return 1;
        }
    }
}
