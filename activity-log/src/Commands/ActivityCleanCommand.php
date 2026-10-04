<?php

declare(strict_types=1);

namespace Bongo\Packages\ActivityLog\Commands;

use Bongo\Packages\ActivityLog\ActivityLog;
use System\Console\Command;

class ActivityCleanCommand extends Command
{
    protected string $name = 'activity:clean';
    protected string $description = 'Clean activity log records older than the specified retention period';

    public function handle(array $args, array $options): int
    {
        $days = isset($options['days']) ? (int) $options['days'] : null;
        $count = ActivityLog::clean($days);

        $this->info("Cleaned {$count} activity log record(s) from database.");
        return 0;
    }
}
