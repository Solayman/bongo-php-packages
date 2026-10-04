<?php

namespace Bongo\Packages\Nagad\Commands;

use Bongo\Packages\Nagad\Nagad;
use System\Console\Command;
use Throwable;

class NagadCheckCommand extends Command
{
    protected string $name = 'nagad:check';
    protected string $description = 'Verify Nagad credentials, RSA keys, and API endpoint connectivity';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking Nagad configuration...");

        $merchantId = (string) env('NAGAD_MERCHANT_ID', '');
        $privateKey = (string) env('NAGAD_MERCHANT_PRIVATE_KEY', '');
        $publicKey = (string) env('NAGAD_PUBLIC_KEY', '');
        $sandbox = (bool) env('NAGAD_SANDBOX', true);

        if ($merchantId === '' || $privateKey === '' || $publicKey === '') {
            $this->error("Missing credentials in .env:");
            $this->line("  NAGAD_MERCHANT_ID=your_merchant_id");
            $this->line("  NAGAD_MERCHANT_PRIVATE_KEY=path/to/private.pem (or raw PEM)");
            $this->line("  NAGAD_PUBLIC_KEY=path/to/nagad_public.pem (or raw PEM)");
            $this->line("  NAGAD_SANDBOX=true");
            $this->line("");
            $this->line("Tip: run 'php bongo nagad:keys' to generate a test 2048-bit RSA keypair.");
            return 1;
        }

        $this->line("  Merchant ID: " . $merchantId);
        $this->line("  Mode:        " . ($sandbox ? "Sandbox (Testing)" : "Live (Production)"));

        try {
            $gateway = new Nagad();

            // Validate private key
            $privRes = $gateway->loadPrivateKey($privateKey);
            $privDetails = openssl_pkey_get_details($privRes);
            $this->line("  Private Key: Valid RSA (" . ($privDetails['bits'] ?? 'unknown') . " bits)");

            // Validate public key
            $pubRes = $gateway->loadPublicKey($publicKey);
            $pubDetails = openssl_pkey_get_details($pubRes);
            $this->line("  Public Key:  Valid RSA (" . ($pubDetails['bits'] ?? 'unknown') . " bits)");

            $url = $gateway->getBaseUrl();
            $this->line("  Endpoint:    $url");

            // Ping endpoint
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $context);

            if ($body !== false) {
                $this->info("Nagad gateway endpoint is reachable.");
                return 0;
            } else {
                $this->warn("Gateway endpoint returned no response (check internet connectivity).");
                return 0;
            }
        } catch (Throwable $e) {
            $this->error("Configuration verification failed: " . $e->getMessage());
            return 1;
        }
    }
}
