<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Checks;

class DiskSpaceCheck extends Check
{
    public function name(): string
    {
        return 'disk_space';
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $path = function_exists('base_path') ? base_path() : '.';

        try {
            $freeBytes = @disk_free_space($path);
            $totalBytes = @disk_total_space($path);

            if ($freeBytes === false || $totalBytes === false || $totalBytes <= 0) {
                return CheckResult::warning($this->name(), 'Unable to read disk space metrics.');
            }

            $freeMb = round($freeBytes / (1024 * 1024), 2);
            $totalMb = round($totalBytes / (1024 * 1024), 2);
            $freePercent = round(($freeBytes / $totalBytes) * 100, 1);

            $minFreeMb = function_exists('config') ? (int) config('health.disk_min_free_mb', 500) : 500;
            $latency = (microtime(true) - $start) * 1000.0;

            if ($freeMb < $minFreeMb) {
                return CheckResult::failed(
                    $this->name(),
                    "Low disk space: {$freeMb} MB free (below {$minFreeMb} MB threshold)",
                    $latency,
                    ['free_mb' => $freeMb, 'total_mb' => $totalMb, 'free_percent' => $freePercent]
                );
            }

            if ($freePercent < 10.0) {
                return CheckResult::warning(
                    $this->name(),
                    "Disk space warning: only {$freePercent}% free space remaining",
                    $latency,
                    ['free_mb' => $freeMb, 'total_mb' => $totalMb, 'free_percent' => $freePercent]
                );
            }

            return CheckResult::ok(
                $this->name(),
                "Disk space healthy: {$freeMb} MB free ({$freePercent}%)",
                $latency,
                ['free_mb' => $freeMb, 'total_mb' => $totalMb, 'free_percent' => $freePercent]
            );
        } catch (\Throwable $e) {
            $latency = (microtime(true) - $start) * 1000.0;
            return CheckResult::failed($this->name(), 'Disk space check failed: ' . $e->getMessage(), $latency);
        }
    }
}
