<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup\Commands;

use Bongo\Packages\Backup\Backup;
use System\Console\Command;

class BackupRunCommand extends Command
{
    protected string $name = 'backup:run';
    protected string $description = 'Create a new backup archive of database and application files';

    public function handle(array $args, array $options): int
    {
        $this->info("Initiating Bongo application backup...");

        $runOptions = [
            'db_only' => isset($options['db-only']),
            'files_only' => isset($options['files-only']),
            'disk' => $options['disk'] ?? null,
            'connection' => $options['database'] ?? null,
        ];

        $result = Backup::run($runOptions);

        foreach ($result->messages() as $msg) {
            $this->line("  {$msg}");
        }

        if (!$result->isSuccessful()) {
            $this->error("Backup failed!");
            return 1;
        }

        $this->info("Backup completed in {$result->duration()}s. File: {$result->path()} ({$result->humanSize()})");
        return 0;
    }
}
