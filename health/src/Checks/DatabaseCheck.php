<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Checks;

use System\Database\DB;

class DatabaseCheck extends Check
{
    public function name(): string
    {
        return 'database';
    }

    public function run(): CheckResult
    {
        $start = microtime(true);

        try {
            $conn = DB::connection();
            $pdo = $conn->getPdo();
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetchColumn();

            $latency = (microtime(true) - $start) * 1000.0;
            $maxLatency = function_exists('config') ? (float) config('health.database_max_latency_ms', 200.0) : 200.0;

            if ($latency > $maxLatency) {
                return CheckResult::warning(
                    $this->name(),
                    "Database response is slow ({$latency}ms > {$maxLatency}ms threshold)",
                    $latency,
                    ['driver' => $conn->getDriver()]
                );
            }

            return CheckResult::ok(
                $this->name(),
                'Database connection is healthy and responsive',
                $latency,
                ['driver' => $conn->getDriver()]
            );
        } catch (\Throwable $e) {
            $latency = (microtime(true) - $start) * 1000.0;
            return CheckResult::failed(
                $this->name(),
                'Database connection failed: ' . $e->getMessage(),
                $latency
            );
        }
    }
}
