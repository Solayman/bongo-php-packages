<?php

declare(strict_types=1);

namespace Bongo\Packages\Paypal\Commands;

use Bongo\Packages\Paypal\Paypal;
use System\Console\Command;
use Throwable;

class PaypalCheckCommand extends Command
{
    protected string $name = 'paypal:check';
    protected string $description = 'Verify PayPal API credentials, OAuth token generation, and endpoint connectivity';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking PayPal configuration...");

        $mode = (string) env('PAYPAL_MODE', 'sandbox');
        $clientId = (string) env('PAYPAL_CLIENT_ID', '');
        $clientSecret = (string) env('PAYPAL_CLIENT_SECRET', '');
        $webhookId = (string) env('PAYPAL_WEBHOOK_ID', '');
        $currency = (string) env('PAYPAL_CURRENCY', 'USD');

        if ($clientId === '' || $clientSecret === '') {
            $this->error("Missing PayPal credentials in .env:");
            $this->line("  PAYPAL_MODE=sandbox (or live)");
            $this->line("  PAYPAL_CLIENT_ID=your_client_id");
            $this->line("  PAYPAL_CLIENT_SECRET=your_client_secret");
            $this->line("  PAYPAL_WEBHOOK_ID=your_webhook_id (optional for webhooks)");
            $this->line("  PAYPAL_CURRENCY=USD");
            return 1;
        }

        $isLive = strtolower($mode) === 'live';
        $baseUrl = $isLive ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        $this->line("  Mode:            " . ($isLive ? "\033[32mLive / Production\033[0m" : "\033[33mSandbox / Test\033[0m"));
        $this->line("  Base URL:        " . $baseUrl);
        $this->line("  Client ID:       " . substr($clientId, 0, 10) . '...' . substr($clientId, -4));
        $this->line("  Client Secret:   " . substr($clientSecret, 0, 6) . '****************');
        $this->line("  Webhook ID:      " . ($webhookId !== '' ? substr($webhookId, 0, 10) . '...' : "\033[33mNot set\033[0m"));
        $this->line("  Default Currency:" . strtoupper($currency));

        $this->line("");
        $this->info("Verifying PayPal OAuth 2.0 authentication...");

        try {
            $t0 = microtime(true);
            $ch = curl_init($baseUrl . '/v1/oauth2/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'User-Agent: Bongo-PayPal/1.0.0',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $body = curl_exec($ch);
            $duration = round((microtime(true) - $t0) * 1000, 1);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                $this->error("Connection failed: {$curlError} ({$duration}ms)");
                return 1;
            }

            $data = json_decode((string) $body, true);

            if ($status === 200 && !empty($data['access_token'])) {
                $this->info("  [OK] Authentication successful ({$duration}ms)");
                $this->line("  Token Type:      " . ($data['token_type'] ?? 'Bearer'));
                $this->line("  Expires In:      " . ($data['expires_in'] ?? 32400) . " seconds");
                $this->line("  App ID:          " . ($data['app_id'] ?? 'N/A'));
                return 0;
            }

            $errorMsg = $data['error_description'] ?? $data['message'] ?? "HTTP {$status}";
            $this->error("  [FAILED] PayPal API rejected credentials (HTTP {$status}): {$errorMsg}");
            return 1;
        } catch (Throwable $e) {
            $this->error("Unexpected error: " . $e->getMessage());
            return 1;
        }
    }
}
