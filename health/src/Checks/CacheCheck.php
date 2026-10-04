<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Checks;

class CacheCheck extends Check
{
    public function name(): string
    {
        return 'cache';
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $key = 'health_probe_' . uniqid();
        $val = 'bongo_test_' . time();

        try {
            if (function_exists('cache')) {
                cache([$key => $val], 60);
                $retrieved = cache($key);
                if (function_exists('cache_forget')) {
                    cache_forget($key);
                }

                $latency = (microtime(true) - $start) * 1000.0;
                if ($retrieved === $val) {
                    return CheckResult::ok($this->name(), 'Cache driver is responsive', $latency);
                }
            }

            // Fallback: Test cache directory writeability
            $cacheDir = function_exists('storage_path') ? storage_path('cache') : 'storage/cache';
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            $tmp = $cacheDir . '/.health_test_' . uniqid() . '.tmp';
            file_put_contents($tmp, $val);
            $read = file_get_contents($tmp);
            @unlink($tmp);

            $latency = (microtime(true) - $start) * 1000.0;
            if ($read === $val) {
                return CheckResult::ok($this->name(), 'Cache storage is responsive', $latency);
            }

            return CheckResult::failed($this->name(), 'Cache read/write verification failed', $latency);
        } catch (\Throwable $e) {
            $latency = (microtime(true) - $start) * 1000.0;
            return CheckResult::failed($this->name(), 'Cache check failed: ' . $e->getMessage(), $latency);
        }
    }
}
