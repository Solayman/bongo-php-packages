<?php

namespace Bongo\Packages\Webhooks\Commands;

use Bongo\Packages\Webhooks\Webhook;
use System\Console\Command;

class WebhookSendCommand extends Command
{
    protected string $name = 'webhook:send';
    protected string $description = 'Dispatch a webhook test payload to a specified URL';

    public function handle(array $args, array $options): int
    {
        $url = $args[0] ?? null;
        $event = $args[1] ?? 'test.ping';

        if (!$url) {
            $this->error("Usage: php bongo webhook:send <url> [event]");
            return 1;
        }

        $payload = [
            'event'     => $event,
            'timestamp' => time(),
            'message'   => 'Bongo Framework Webhook Ping',
        ];

        $this->info("Dispatching webhook [{$event}] to: {$url}...");
        $res = Webhook::dispatch($url, $event, $payload);

        if ($res['success']) {
            $this->info("Webhook delivered successfully! Status: {$res['status_code']}");
            return 0;
        }

        $this->error("Webhook delivery failed! Status: {$res['status_code']} - " . ($res['error'] ?? ''));
        return 1;
    }
}
