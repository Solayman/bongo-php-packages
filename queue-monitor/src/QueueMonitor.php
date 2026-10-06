<?php

namespace Bongo\Packages\QueueMonitor;

use System\Database\DB;
use System\Database\Schema\Schema;
use System\Queue\Queue;
use System\Queue\Worker;

class QueueMonitor
{
    /** Pending, delayed, reserved and failed jobs from the core queue (database or redis driver) */
    public static function stats(): array
    {
        return Queue::stats() + [
            'driver'    => Queue::driver(),
            'status'    => 'active',
            'timestamp' => time(),
        ];
    }

    public static function failedJobs(int $limit = 50): array
    {
        try {
            if (!Schema::hasTable('failed_jobs')) {
                return [];
            }
            return DB::table('failed_jobs')->orderBy('id', 'desc')->limit($limit)->get();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Put one failed job back on its queue (the core's queue:retry, so redis entries are signed too) */
    public static function retry(int|string $id): bool
    {
        try {
            return Worker::retry((int) $id) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Put every failed job back on its queue in one pass */
    public static function retryAll(): int
    {
        try {
            return Worker::retry('all');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function delete(int|string $id): bool
    {
        try {
            if (!Schema::hasTable('failed_jobs')) {
                return false;
            }
            return (bool) DB::table('failed_jobs')->where('id', $id)->delete();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function flush(): int
    {
        try {
            if (!Schema::hasTable('failed_jobs')) {
                return 0;
            }
            $count = (int) DB::table('failed_jobs')->count();
            DB::table('failed_jobs')->delete(true);
            return $count;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
