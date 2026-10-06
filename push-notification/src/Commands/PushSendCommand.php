<?php

declare(strict_types=1);

namespace Bongo\Packages\PushNotification\Commands;

use Bongo\Packages\PushNotification\Push;
use System\Console\Command;

class PushSendCommand extends Command
{
    protected string $name = 'push:send';
    protected string $description = 'Send a test push notification via Web Push or FCM';

    public function handle(array $args, array $options): int
    {
        $target = $args[0] ?? ($options['target'] ?? null);
        $title = $args[1] ?? ($options['title'] ?? 'Bongo Notification');
        $body = $args[2] ?? ($options['body'] ?? 'Hello from Bongo PHP Push Engine!');

        if (!$target) {
            $this->error('Usage: php bongo push:send <subscription_endpoint_or_fcm_token> [title] [body]');
            return 1;
        }

        $isFcm = (strlen($target) > 50 && strpos($target, 'http') === false);
        $this->info("Dispatching test notification to {$target} via " . ($isFcm ? 'FCM' : 'WebPush') . "...");

        try {
            if ($isFcm) {
                $res = push()->sendFcm($target, ['title' => $title, 'body' => $body]);
            } else {
                $res = push()->sendWebPush($target, ['title' => $title, 'body' => $body]);
            }

            $this->info("✓ Push notification dispatched successfully!");
            $this->line(json_encode($res, JSON_PRETTY_PRINT));
            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to send push notification: " . $e->getMessage());
            return 1;
        }
    }
}
