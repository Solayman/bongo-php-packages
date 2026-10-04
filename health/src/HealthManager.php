<?php

declare(strict_types=1);

namespace Bongo\Packages\Health;

use Bongo\Packages\Health\Checks\CacheCheck;
use Bongo\Packages\Health\Checks\Check;
use Bongo\Packages\Health\Checks\CheckResult;
use Bongo\Packages\Health\Checks\DatabaseCheck;
use Bongo\Packages\Health\Checks\DiskSpaceCheck;
use Bongo\Packages\Health\Checks\RedisCheck;
use Bongo\Packages\Health\Checks\StorageCheck;

class HealthManager
{
    protected static ?self $instance = null;

    /**
     * @var array<string, Check|callable>
     */
    protected array $checks = [];

    public function __construct()
    {
        $this->registerDefaultChecks();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function registerDefaultChecks(): self
    {
        $this->register(new DatabaseCheck());
        $this->register(new StorageCheck());
        $this->register(new DiskSpaceCheck());
        $this->register(new CacheCheck());
        $this->register(new RedisCheck());

        return $this;
    }

    /**
     * Register a new custom check.
     */
    public function register(Check|callable $check, ?string $name = null): self
    {
        if ($check instanceof Check) {
            $key = $check->name();
            $this->checks[$key] = $check;
        } elseif (is_callable($check)) {
            $key = $name ?? ('custom_' . count($this->checks));
            $this->checks[$key] = $check;
        }

        return $this;
    }

    public function clearChecks(): self
    {
        $this->checks = [];
        return $this;
    }

    /**
     * Run all registered checks and return consolidated diagnostic payload.
     *
     * @return array{status: string, timestamp: string, duration_ms: float, checks: array<string, array>}
     */
    public function run(): array
    {
        $startTime = microtime(true);
        $results = [];
        $overallStatus = 'healthy';

        foreach ($this->checks as $name => $check) {
            $res = null;

            if ($check instanceof Check) {
                $res = $check->run();
            } elseif (is_callable($check)) {
                $checkStart = microtime(true);
                try {
                    $raw = $check();
                    $latency = (microtime(true) - $checkStart) * 1000.0;
                    if ($raw instanceof CheckResult) {
                        $res = $raw;
                    } elseif ($raw === true) {
                        $res = CheckResult::ok($name, 'Custom check passed', $latency);
                    } elseif ($raw === false) {
                        $res = CheckResult::failed($name, 'Custom check failed', $latency);
                    } elseif (is_string($raw)) {
                        $res = CheckResult::ok($name, $raw, $latency);
                    } else {
                        $res = CheckResult::ok($name, 'Check passed', $latency);
                    }
                } catch (\Throwable $e) {
                    $latency = (microtime(true) - $checkStart) * 1000.0;
                    $res = CheckResult::failed($name, 'Exception: ' . $e->getMessage(), $latency);
                }
            }

            if ($res !== null) {
                $results[$res->name] = $res->toArray();

                if ($res->isFailed()) {
                    $overallStatus = 'unhealthy';
                } elseif ($res->status === CheckResult::STATUS_WARNING && $overallStatus !== 'unhealthy') {
                    $overallStatus = 'warning';
                }
            }
        }

        $totalDuration = round((microtime(true) - $startTime) * 1000.0, 2);

        return [
            'status' => $overallStatus,
            'timestamp' => date('c'),
            'duration_ms' => $totalDuration,
            'checks' => $results,
        ];
    }
}
