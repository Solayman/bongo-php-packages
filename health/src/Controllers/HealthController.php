<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Controllers;

use Bongo\Packages\Health\Health;
use System\Http\Request;

class HealthController
{
    public function check(Request $request): array
    {
        $report = Health::run();

        $secretToken = function_exists('config') ? config('health.secret_token') : null;
        $isAuthorized = empty($secretToken);

        if (!$isAuthorized) {
            $token = $request->query('token') ?? $request->bearerToken();
            if ($token && hash_equals((string) $secretToken, (string) $token)) {
                $isAuthorized = true;
            }
        }

        $statusCode = ($report['status'] === 'unhealthy') ? 503 : 200;
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }

        if (!$isAuthorized) {
            // Mask detailed server diagnostics from public viewers
            return [
                'status' => $report['status'],
                'timestamp' => $report['timestamp'],
            ];
        }

        return $report;
    }
}
