<?php

declare(strict_types=1);

namespace Bongo\Packages\TwoFactor\Commands;

use Bongo\Packages\TwoFactor\TwoFactor;
use System\Console\Command;

class TwoFactorGenerateCommand extends Command
{
    protected string $name = '2fa:generate';
    protected string $description = 'Generate a new TOTP secret, QR URI, and current 6-digit verification code';

    public function handle(array $args, array $options): int
    {
        $this->info("Generating new Two-Factor Authentication credentials...");

        $secret = TwoFactor::generateSecret(16);
        $issuer = (string) (function_exists('config') ? config('two_factor.issuer') : 'Bongo');
        $label = $args[0] ?? 'admin@example.com';
        $qrUrl = TwoFactor::qrCodeUrl($label, $secret, $issuer);
        $currentCode = TwoFactor::generateTotp($secret);

        $this->line("  Issuer:       {$issuer}");
        $this->line("  Account:      {$label}");
        $this->line("  Secret Key:   \033[32m{$secret}\033[0m");
        $this->line("  Current Code: \033[33m{$currentCode}\033[0m (valid for next ~30s)");
        $this->line("  otpauth URI:  {$qrUrl}");

        $this->line("");
        $this->line("Recovery Backup Codes:");
        $codes = TwoFactor::generateRecoveryCodes(8);
        foreach ($codes as $code) {
            $this->line("  - {$code}");
        }

        return 0;
    }
}
