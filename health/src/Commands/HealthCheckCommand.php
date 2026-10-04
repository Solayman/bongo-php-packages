<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Commands;

use Bongo\Packages\Health\Health;
use System\Console\Command;

class HealthCheckCommand extends Command
{
    protected string $name = 'health:check';
    protected string $description = 'Perform production health checks on database, storage, cache, and services';

    public function handle(array $args, array $options): int
    {
        $report = Health::run();

        if (isset($options['json'])) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return ($report['status'] === 'unhealthy') ? 1 : 0;
        }

        $statusColor = match ($report['status']) {
            'healthy' => 'info',
            'warning' => 'warn',
            default => 'error',
        };

        $this->{$statusColor}("Application Health: " . strtoupper($report['status']) . " (" . count($report['checks']) . " checks in {$report['duration_ms']}ms)");
        $this->line(str_repeat('-', 75));
        printf("%-15s | %-10s | %-10s | %s\n", "CHECK", "STATUS", "LATENCY", "MESSAGE");
        $this->line(str_repeat('-', 75));

        foreach ($report['checks'] as $c) {
            $name = $c['name'];
            $status = strtoupper($c['status']);
            $latency = $c['latency_ms'] > 0 ? "{$c['latency_ms']}ms" : "-";
            $msg = $c['message'];
            if (strlen($msg) > 35) {
                $msg = substr($msg, 0, 32) . '...';
            }

            printf("%-15s | %-10s | %-10s | %s\n", $name, $status, $latency, $msg);
        }
        $this->line(str_repeat('-', 75));

        return ($report['status'] === 'unhealthy') ? 1 : 0;
    }
}
