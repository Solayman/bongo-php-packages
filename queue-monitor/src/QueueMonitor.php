<?php

namespace Bongo\Packages\QueueMonitor;

use System\Database\DB;
use System\Database\Schema\Schema;
use System\Queue\Queue;

class QueueMonitor
{
    public static function stats(): array
    {
        $driver = config('queue.default', 'sync');
        $pending = 0;
        $failed = 0;

        try {
            if ($driver === 'database' && Schema::hasTable('jobs')) {
                $pending = (int) DB::table('jobs')->whereNull('reserved_at')->count();
            } elseif ($driver === 'redis' && class_exists('Redis')) {
                // Approximate redis queue length
                try {
                    $redis = new \Redis();
                    $redis->connect(config('database.redis.host', '127.0.0.1'), (int) config('database.redis.port', 6379), 0.5);
                    $pending = (int) $redis->lLen('queues:default');
                    $redis->close();
                } catch (\Throwable $e) {
                    $pending = 0;
                }
            }
        } catch (\Throwable $e) {
            $pending = 0;
        }

        try {
            if (Schema::hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')->count();
            }
        } catch (\Throwable $e) {
            $failed = 0;
        }

        return [
            'driver'    => $driver,
            'pending'   => $pending,
            'failed'    => $failed,
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

    public static function retry(int|string $id): bool
    {
        try {
            if (!Schema::hasTable('failed_jobs')) {
                return false;
            }

            $job = DB::table('failed_jobs')->where('id', $id)->first();
            if (!$job) {
                return false;
            }

            $payload = json_decode($job->payload ?? '{}', true);
            if (!empty($payload['job'])) {
                $jobClass = $payload['job'];
                $jobData = $payload['data'] ?? [];
                if (class_exists($jobClass)) {
                    $instance = is_array($jobData) ? new $jobClass(...array_values($jobData)) : new $jobClass($jobData);
                    Queue::push($instance);
                } else {
                    Queue::pushRaw($job->payload);
                }
            } else {
                Queue::pushRaw($job->payload);
            }

            DB::table('failed_jobs')->where('id', $id)->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function retryAll(): int
    {
        $failed = static::failedJobs(500);
        $count = 0;
        foreach ($failed as $job) {
            if (static::retry($job->id)) {
                $count++;
            }
        }
        return $count;
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
