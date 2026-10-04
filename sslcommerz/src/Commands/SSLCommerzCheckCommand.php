<?php

declare(strict_types=1);

namespace Bongo\Packages\Sslcommerz\Commands;

use Bongo\Packages\Sslcommerz\SSLCommerz;
use System\Console\Command;
use Throwable;

class SSLCommerzCheckCommand extends Command
{
    protected string $name = 'sslcommerz:check';
    protected string $description = 'Verify SSLCommerz configuration and sandbox connectivity';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking SSLCommerz configuration...");

        $storeId = (string) env('SSLCOMMERZ_STORE_ID', '');
        $storePasswd = (string) env('SSLCOMMERZ_STORE_PASSWORD', '');
        $sandbox = (bool) env('SSLCOMMERZ_SANDBOX', true);

        if ($storeId === '' || $storePasswd === '') {
            $this->error("Missing credentials in .env:");
            $this->line("  SSLCOMMERZ_STORE_ID=your_store_id");
            $this->line("  SSLCOMMERZ_STORE_PASSWORD=your_store_password");
            $this->line("  SSLCOMMERZ_SANDBOX=true");
            return 1;
        }

        $this->line("  Store ID:  " . substr($storeId, 0, 4) . '****');
        $this->line("  Mode:      " . ($sandbox ? "Sandbox (Testing)" : "Live (Production)"));

        try {
            $gateway = new SSLCommerz();
            $url = $gateway->getBaseUrl();
            $this->line("  Endpoint:  $url");

            // Ping endpoint
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $context);

            if ($body !== false) {
                $this->info("SSLCommerz gateway is reachable.");
                return 0;
            } else {
                $this->warn("Gateway endpoint returned no response (check internet connectivity).");
                return 1;
            }
        } catch (Throwable $e) {
            $this->error("Connection failed: " . $e->getMessage());
            return 1;
        }
    }
}
