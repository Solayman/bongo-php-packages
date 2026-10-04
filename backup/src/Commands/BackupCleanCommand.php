<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup\Commands;

use Bongo\Packages\Backup\Backup;
use System\Console\Command;

class BackupCleanCommand extends Command
{
    protected string $name = 'backup:clean';
    protected string $description = 'Clean old backups according to retention policy';

    public function handle(array $args, array $options): int
    {
        $disk = $options['disk'] ?? null;
        $keep = isset($options['keep']) ? (int) $options['keep'] : null;
        $days = isset($options['days']) ? (int) $options['days'] : null;

        $this->info("Cleaning old backups...");
        $deleted = Backup::clean($disk, $keep, $days);

        if ($deleted === 0) {
            $this->line("  No expired backups to clean.");
        } else {
            $this->info("  Successfully deleted {$deleted} expired backup(s).");
        }

        return 0;
    }
}
