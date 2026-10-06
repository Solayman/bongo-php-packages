<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Controllers;

use Bongo\Packages\Health\Health;
use System\Cache\Cache;
use System\Http\Request;

class HealthController
{
    public function check(Request $request): array
    {
        // Every visitor can call this URL: reuse a recent report instead of running all checks
        // (database, disk and cache writes) on each request
        $seconds = (int) (function_exists('config') ? config('health.cache_seconds', 10) : 10);
        try {
            $report = $seconds > 0 ? Cache::remember('health:report', $seconds, fn() => Health::run()) : Health::run();
        } catch (\Throwable $e) {
            $report = Health::run();
        }

        $statusCode = ($report['status'] === 'unhealthy') ? 503 : 200;
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
        }

        if (!$this->authorized()) {
            // Public viewers only learn up/down, never server details
            return [
                'status' => $report['status'],
                'timestamp' => $report['timestamp'],
            ];
        }

        return $report;
    }

    /**
     * Details need HEALTH_SECRET_TOKEN (as "Authorization: Bearer <token>" or ?token=).
     * Without a token configured, details are shown only in debug mode (local development).
     */
    protected function authorized(): bool
    {
        $secret = (string) (function_exists('config') ? config('health.secret_token', '') : '');
        if ($secret === '') {
            return function_exists('config') && (bool) config('app.debug', false);
        }

        $given = (string) Request::query('token', '');
        $authorization = (string) (Request::header('Authorization') ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($given === '' && str_starts_with($authorization, 'Bearer ')) {
            $given = substr($authorization, 7);
        }

        return $given !== '' && hash_equals($secret, $given);
    }
}
