<?php

namespace Bongo\Packages\Discord\Commands;

use Bongo\Packages\Discord\Discord;
use System\Console\Command;

class DiscordSendCommand extends Command
{
    protected string $name = 'discord:send';
    protected string $description = 'Send a notification message or alert to a Discord webhook';

    public function handle(array $args, array $options): int
    {
        $message = $args[0] ?? null;
        if (!$message) {
            $this->error("Usage: php bongo discord:send \"<message>\" [--webhook=URL] [--alert]");
            return 1;
        }

        $webhookUrl = $options['webhook'] ?? null;
        $isAlert = isset($options['alert']);

        $this->info("Sending message to Discord...");
        try {
            if ($isAlert) {
                $res = Discord::alert('CLI Notification', $message, 'info', [], $webhookUrl);
            } else {
                $res = Discord::send($message, $webhookUrl);
            }

            if ($res['success']) {
                $this->info("Message sent successfully!");
                return 0;
            }

            $this->error("Failed to send Discord message. Status: {$res['status_code']} - " . ($res['error'] ?? ''));
            return 1;
        } catch (\Throwable $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}
