<?php

declare(strict_types=1);

namespace Bongo\Packages\WhatsApp\Commands;

use Bongo\Packages\WhatsApp\WhatsApp;
use System\Console\Command;

class WhatsAppSendCommand extends Command
{
    protected string $name = 'whatsapp:send';
    protected string $description = 'Send a test WhatsApp message via Meta Cloud API';

    public function handle(array $args, array $options): int
    {
        $to = $args[0] ?? ($options['to'] ?? null);
        $message = $args[1] ?? ($options['message'] ?? 'Hello from Bongo PHP!');

        if (!$to) {
            $this->error('Usage: php bongo whatsapp:send <to_phone_number> [message]');
            return 1;
        }

        $this->info("Sending WhatsApp message to {$to}...");

        try {
            $res = whatsapp()->sendText((string) $to, (string) $message);
            $msgId = $res['messages'][0]['id'] ?? 'unknown';
            $this->info("✓ Message sent successfully! Message ID: {$msgId}");
            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to send WhatsApp message: " . $e->getMessage());
            return 1;
        }
    }
}
