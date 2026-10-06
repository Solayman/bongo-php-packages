<?php

declare(strict_types=1);

namespace Bongo\Packages\Honeypot\Commands;

use Bongo\Packages\Honeypot\Honeypot;
use System\Console\Command;

class HoneypotCheckCommand extends Command
{
    protected string $signature = 'honeypot:check';
    protected string $description = 'Display Honeypot anti-spam configuration and generate a test token';

    public function handle(array $args, array $options): int
    {
        $hp = Honeypot::instance();

        $this->info("Honeypot Anti-Spam Status:");
        $this->line("  Decoy Trap Field:    " . $hp->decoyField());
        $this->line("  Timestamp Field:     " . $hp->timeField());
        $this->line("  Minimum Time Gate:   " . $hp->minSeconds() . " seconds");

        $token = $hp->generateToken();
        $this->line("\nGenerated Test Token:");
        $this->line("  " . $token);

        return 0;
    }
}
