<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup\Commands;

use Bongo\Packages\Backup\Backup;
use System\Console\Command;

class BackupListCommand extends Command
{
    protected string $name = 'backup:list';
    protected string $description = 'List all existing application backups';

    public function handle(array $args, array $options): int
    {
        $disk = $options['disk'] ?? null;
        $backups = Backup::list($disk);

        if (empty($backups)) {
            $this->warn("No backups found" . ($disk ? " on disk [{$disk}]." : "."));
            return 0;
        }

        $this->info("Found " . count($backups) . " backup(s):");
        $this->line(sprintf("  %-40s %-12s %-20s %-8s", "FILENAME", "SIZE", "DATE", "DISK"));
        $this->line("  " . str_repeat("-", 84));

        foreach ($backups as $b) {
            $this->line(sprintf(
                "  %-40s %-12s %-20s %-8s",
                $b['filename'],
                $b['human_size'],
                $b['date'],
                $b['disk']
            ));
        }

        return 0;
    }
}
