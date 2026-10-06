<?php

declare(strict_types=1);

namespace Bongo\Packages\ActivityLog;

use Bongo\Packages\ActivityLog\Models\Activity;

class ActivityLog
{
    public static function log(?string $logName = null): ActivityLogger
    {
        return new ActivityLogger($logName);
    }

    public static function on(mixed $subject): ActivityLogger
    {
        return (new ActivityLogger())->performedOn($subject);
    }

    public static function by(mixed $causer): ActivityLogger
    {
        return (new ActivityLogger())->causedBy($causer);
    }

    public static function clean(?int $days = null): int
    {
        $retentionDays = $days ?? (function_exists('config') ? (int) config('activitylog.delete_records_older_than_days', 60) : 60);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        // Two queries in total instead of loading every old row and deleting it one by one
        $count = Activity::where('created_at', '<', $cutoff)->count();
        if ($count > 0) {
            Activity::where('created_at', '<', $cutoff)->delete();
        }

        return $count;
    }
}
