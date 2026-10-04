<?php

namespace Bongo\Packages\Sms\Commands;

use Bongo\Packages\Sms\SMS;
use System\Console\Command;
use Throwable;

class SMSSendCommand extends Command
{
    protected string $name = 'sms:send';
    protected string $description = 'Send a test SMS message via the configured gateway';

    public function handle(array $args, array $options): int
    {
        $phone = $args[0] ?? null;
        $message = $args[1] ?? 'Test message from Bongo SMS';

        if (!$phone) {
            $this->error("Usage: php bongo sms:send <phone> [message] [--driver=]");
            return 1;
        }

        $driver = $options['driver'] ?? null;
        $this->info("Sending SMS to $phone...");

        try {
            $res = SMS::send($phone, $message, $driver);
            if ($res['success']) {
                $this->info("SMS dispatched successfully!");
                if ($res['id']) {
                    $this->line("  Message ID: " . $res['id']);
                }
                return 0;
            } else {
                $this->error("Failed to send SMS: " . ($res['error'] ?? 'Unknown error'));
                return 1;
            }
        } catch (Throwable $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}
