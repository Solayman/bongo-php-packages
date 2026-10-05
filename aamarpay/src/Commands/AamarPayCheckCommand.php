<?php

declare(strict_types=1);

namespace Bongo\Packages\Aamarpay\Commands;

use Bongo\Packages\Aamarpay\AamarPay;
use System\Console\Command;
use Throwable;

class AamarPayCheckCommand extends Command
{
    protected string $name = 'aamarpay:check';
    protected string $description = 'Verify AamarPay merchant configuration and gateway connectivity';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking AamarPay configuration...");

        $storeId = (string) env('AAMARPAY_STORE_ID', 'aamarpaytest');
        $signatureKey = (string) env('AAMARPAY_SIGNATURE_KEY', '28c78bb1f45112f5a40b956f9d102abb');
        $sandbox = (bool) env('AAMARPAY_SANDBOX', true);

        if ($storeId === '' || $signatureKey === '') {
            $this->error("Missing credentials in .env:");
            $this->line("  AAMARPAY_STORE_ID=your_store_id");
            $this->line("  AAMARPAY_SIGNATURE_KEY=your_signature_key");
            $this->line("  AAMARPAY_SANDBOX=true");
            return 1;
        }

        $this->line("  Store ID:      " . $storeId);
        $this->line("  Signature Key: " . substr($signatureKey, 0, 4) . '****' . substr($signatureKey, -4));
        $this->line("  Mode:          " . ($sandbox ? "Sandbox (Testing)" : "Live (Production)"));

        try {
            $gateway = new AamarPay();
            $url = $gateway->getBaseUrl();
            $this->line("  Gateway URL:   $url");

            // Ping endpoint
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $context);

            if ($body !== false) {
                $this->info("AamarPay gateway is online and reachable.");
                return 0;
            } else {
                $this->warn("AamarPay endpoint returned no response (check network).");
                return 1;
            }
        } catch (Throwable $e) {
            $this->error("Connection failed: " . $e->getMessage());
            return 1;
        }
    }
}
