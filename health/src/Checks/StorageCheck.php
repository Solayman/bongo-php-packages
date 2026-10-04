<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Checks;

class StorageCheck extends Check
{
    public function name(): string
    {
        return 'storage';
    }

    public function run(): CheckResult
    {
        $start = microtime(true);
        $storageDir = function_exists('storage_path') ? storage_path('framework') : 'storage';

        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }

        $probeFile = $storageDir . '/.health_probe_' . uniqid() . '.tmp';
        $testPayload = 'bongo_health_probe_' . time();

        try {
            // 1. Test write
            if (file_put_contents($probeFile, $testPayload) === false) {
                return CheckResult::failed($this->name(), "Storage path [{$storageDir}] is not writable.");
            }

            // 2. Test read
            $read = file_get_contents($probeFile);
            if ($read !== $testPayload) {
                @unlink($probeFile);
                return CheckResult::failed($this->name(), "Storage probe data read mismatch.");
            }

            // 3. Test delete
            @unlink($probeFile);

            $latency = (microtime(true) - $start) * 1000.0;
            return CheckResult::ok(
                $this->name(),
                'Storage directory is writable and readable',
                $latency,
                ['path' => $storageDir]
            );
        } catch (\Throwable $e) {
            if (file_exists($probeFile)) {
                @unlink($probeFile);
            }
            $latency = (microtime(true) - $start) * 1000.0;
            return CheckResult::failed($this->name(), 'Storage check failed: ' . $e->getMessage(), $latency);
        }
    }
}
