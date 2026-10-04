<?php

declare(strict_types=1);

namespace Bongo\Packages\Stripe\Commands;

use Bongo\Packages\Stripe\Stripe;
use System\Console\Command;
use Throwable;

class StripeCheckCommand extends Command
{
    protected string $name = 'stripe:check';
    protected string $description = 'Verify Stripe API credentials, webhook secrets, and endpoint connectivity';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking Stripe configuration...");

        $publishableKey = (string) env('STRIPE_KEY', '');
        $secretKey = (string) env('STRIPE_SECRET', '');
        $webhookSecret = (string) env('STRIPE_WEBHOOK_SECRET', '');
        $currency = (string) env('STRIPE_CURRENCY', 'usd');

        if ($secretKey === '') {
            $this->error("Missing credentials in .env:");
            $this->line("  STRIPE_KEY=pk_test_... (Publishable key)");
            $this->line("  STRIPE_SECRET=sk_test_... (Secret key)");
            $this->line("  STRIPE_WEBHOOK_SECRET=whsec_... (Webhook secret)");
            $this->line("  STRIPE_CURRENCY=usd");
            return 1;
        }

        $isTestMode = str_starts_with($secretKey, 'sk_test_') || str_starts_with($publishableKey, 'pk_test_');
        $this->line("  Mode:            " . ($isTestMode ? "Test / Sandbox" : "Live / Production"));
        $this->line("  Publishable Key: " . ($publishableKey !== '' ? substr($publishableKey, 0, 12) . '...' : "\033[33mNot set\033[0m"));
        $this->line("  Secret Key:      " . substr($secretKey, 0, 10) . '...');
        $this->line("  Webhook Secret:  " . ($webhookSecret !== '' ? substr($webhookSecret, 0, 10) . '...' : "\033[33mNot set\033[0m"));
        $this->line("  Default Currency:" . strtoupper($currency));

        $this->line("");
        $this->info("Verifying Stripe API authentication...");

        try {
            $ch = curl_init('https://api.stripe.com/v1/balance');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $secretKey,
                'User-Agent: Bongo-Stripe/1.0.0',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $t0 = microtime(true);
            $body = curl_exec($ch);
            $duration = round((microtime(true) - $t0) * 1000, 1);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($body === false || !empty($curlError)) {
                $this->error("Connection to Stripe API failed: {$curlError}");
                return 1;
            }

            $data = json_decode((string) $body, true);

            if ($status === 200 && isset($data['object']) && $data['object'] === 'balance') {
                $this->info("✔ Stripe API credentials verified successfully ({$duration}ms)!");
                if (isset($data['available'][0])) {
                    $av = $data['available'][0];
                    $bal = number_format(((float) $av['amount']) / 100, 2);
                    $curr = strtoupper($av['currency'] ?? 'usd');
                    $this->line("  Account Available Balance: {$curr} {$bal}");
                }
                return 0;
            }

            if (isset($data['error'])) {
                $this->error("Stripe Authentication Failed (HTTP {$status}): " . ($data['error']['message'] ?? 'Unknown error'));
                return 1;
            }

            $this->error("Unexpected response from Stripe (HTTP {$status}): {$body}");
            return 1;
        } catch (Throwable $e) {
            $this->error("Stripe diagnostic exception: " . $e->getMessage());
            return 1;
        }
    }
}
