<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Checks;

class RedisCheck extends Check
{
    public function name(): string
    {
        return 'redis';
    }

    public function run(): CheckResult
    {
        $queueDriver = function_exists('env') ? env('QUEUE_DRIVER', 'sync') : 'sync';
        $redisHost = function_exists('env') ? env('REDIS_HOST', null) : null;

        if ($queueDriver !== 'redis' && empty($redisHost)) {
            return CheckResult::skipped($this->name(), 'Redis is not configured as primary queue driver or cache');
        }

        $start = microtime(true);
        $host = $redisHost ?? '127.0.0.1';
        $port = function_exists('env') ? (int) env('REDIS_PORT', 6379) : 6379;
        $timeout = 1.0;

        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if (!$fp) {
                return CheckResult::failed($this->name(), "Cannot connect to Redis at {$host}:{$port} ({$errstr})");
            }
            fwrite($fp, "PING\r\n");
            $response = fgets($fp);
            fclose($fp);

            $latency = (microtime(true) - $start) * 1000.0;
            if (trim((string) $response) === '+PONG') {
                return CheckResult::ok($this->name(), 'Redis server responded with +PONG', $latency, ['host' => $host, 'port' => $port]);
            }

            return CheckResult::warning($this->name(), 'Unexpected Redis response: ' . trim((string) $response), $latency);
        } catch (\Throwable $e) {
            $latency = (microtime(true) - $start) * 1000.0;
            return CheckResult::failed($this->name(), 'Redis connection error: ' . $e->getMessage(), $latency);
        }
    }
}
