<?php

declare(strict_types=1);

namespace Bongo\Packages\PushNotification\Commands;

use Bongo\Packages\PushNotification\Push;
use System\Console\Command;

class PushVapidCommand extends Command
{
    protected string $name = 'push:vapid';
    protected string $description = 'Create a VAPID key pair for Web Push (add both lines to .env once)';

    public function handle(array $args, array $options): int
    {
        $keys = Push::generateVapidKeys();
        $this->info('Add these to .env (keep the private key secret; changing it breaks existing subscriptions):');
        $this->line('WEBPUSH_PUBLIC_KEY=' . $keys['public_key']);
        $this->line('WEBPUSH_PRIVATE_KEY=' . $keys['private_key']);
        $this->line('');
        $this->line('Use the public key as applicationServerKey in pushManager.subscribe() in the browser.');
        return 0;
    }
}
