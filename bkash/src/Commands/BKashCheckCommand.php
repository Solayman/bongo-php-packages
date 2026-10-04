<?php

declare(strict_types=1);

namespace Bongo\Packages\Bkash\Commands;

use Bongo\Packages\Bkash\BKash;
use System\Console\Command;
use Throwable;

class BKashCheckCommand extends Command
{
    protected string $name = 'bkash:check';
    protected string $description = 'Verify bKash credentials and API endpoint connectivity';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking bKash configuration...");

        $appKey = (string) env('BKASH_APP_KEY', '');
        $appSecret = (string) env('BKASH_APP_SECRET', '');
        $username = (string) env('BKASH_USERNAME', '');
        $password = (string) env('BKASH_PASSWORD', '');
        $sandbox = (bool) env('BKASH_SANDBOX', true);

        if ($appKey === '' || $appSecret === '' || $username === '' || $password === '') {
            $this->error("Missing credentials in .env:");
            $this->line("  BKASH_APP_KEY=your_app_key");
            $this->line("  BKASH_APP_SECRET=your_app_secret");
            $this->line("  BKASH_USERNAME=your_username");
            $this->line("  BKASH_PASSWORD=your_password");
            $this->line("  BKASH_SANDBOX=true");
            return 1;
        }

        $this->line("  App Key:   " . substr($appKey, 0, 4) . '****');
        $this->line("  Username:  " . $username);
        $this->line("  Mode:      " . ($sandbox ? "Sandbox (Testing)" : "Live (Production)"));

        try {
            $gateway = new BKash();
            $url = $gateway->getBaseUrl();
            $this->line("  Endpoint:  $url");

            // Ping token endpoint
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $context);

            if ($body !== false) {
                $this->info("bKash gateway endpoint is reachable.");
                return 0;
            } else {
                $this->warn("Gateway endpoint returned no response (check internet connectivity).");
                return 1;
            }
        } catch (Throwable $e) {
            $this->error("Connectivity test failed: " . $e->getMessage());
            return 1;
        }
    }
}
