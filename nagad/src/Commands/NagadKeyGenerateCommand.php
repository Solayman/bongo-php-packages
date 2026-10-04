<?php

declare(strict_types=1);

namespace Bongo\Packages\Nagad\Commands;

use Bongo\Packages\Nagad\Nagad;
use System\Console\Command;
use Throwable;

class NagadKeyGenerateCommand extends Command
{
    protected string $name = 'nagad:keys';
    protected string $description = 'Generate a 2048-bit RSA keypair for Nagad merchant integration';

    public function handle(array $args, array $options): int
    {
        $this->info("Generating 2048-bit RSA keypair for Nagad...");

        try {
            $keys = Nagad::generateKeyPair(2048);

            $save = isset($options['save']) || in_array('--save', $args, true);

            if ($save) {
                $dir = function_exists('storage_path') ? storage_path('nagad') : 'storage/nagad';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $privPath = $dir . '/merchant_private.pem';
                $pubPath = $dir . '/merchant_public.pem';

                file_put_contents($privPath, $keys['private']);
                file_put_contents($pubPath, $keys['public']);

                $this->info("Keys generated and saved successfully:");
                $this->line("  Private key: $privPath");
                $this->line("  Public key:  $pubPath");
                $this->line("");
                $this->line("Add these to your .env file:");
                $this->line("  NAGAD_MERCHANT_PRIVATE_KEY=$privPath");
                $this->line("  # Share $pubPath with Nagad during onboarding.");
            } else {
                $this->info("Generated RSA Keypair (pass --save to write to storage/nagad/):");
                $this->line("");
                $this->line("=== MERCHANT PRIVATE KEY (Keep secret, configure in .env) ===");
                $this->line($keys['private']);
                $this->line("");
                $this->line("=== MERCHANT PUBLIC KEY (Provide to Nagad during onboarding) ===");
                $this->line($keys['public']);
            }

            return 0;
        } catch (Throwable $e) {
            $this->error("Key generation failed: " . $e->getMessage());
            return 1;
        }
    }
}
