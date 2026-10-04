<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Middleware;

use Bongo\Packages\ApiTools\ApiResponse;
use Bongo\Packages\ApiTools\Http\JsonResponse;
use Closure;
use System\Http\Request;

class ApiThrottleMiddleware
{
    /**
     * Handle rate limiting for incoming API requests.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): mixed
    {
        $ip = (string) ($request->ip() ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $authId = '';
        if (function_exists('auth')) {
            $auth = auth();
            if ($auth && method_exists($auth, 'check') && $auth->check() && method_exists($auth, 'id')) {
                $authId = (string) $auth->id();
            }
        }
        $key = 'api_throttle_' . md5($ip . '|' . $authId);

        $decaySeconds = max(1, $decayMinutes * 60);
        $attempts = 0;
        $expiresAt = time() + $decaySeconds;

        $cacheDir = function_exists('base_path') ? base_path('storage/cache/throttle') : sys_get_temp_dir() . '/bongo_throttle';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cacheFile = $cacheDir . '/' . $key . '.json';

        if (file_exists($cacheFile)) {
            $data = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($data) && ($data['expires_at'] ?? 0) > time()) {
                $attempts = (int) ($data['attempts'] ?? 0);
                $expiresAt = (int) ($data['expires_at'] ?? $expiresAt);
            }
        }

        $attempts++;
        @file_put_contents($cacheFile, json_encode([
            'attempts'   => $attempts,
            'expires_at' => $expiresAt,
        ]), LOCK_EX);

        $remaining = max(0, $maxAttempts - $attempts);
        $retryAfter = max(1, $expiresAt - time());

        if ($attempts > $maxAttempts) {
            $response = ApiResponse::error('Too Many Requests', 429, [], 'RATE_LIMIT_EXCEEDED', [
                'Retry-After'           => (string) $retryAfter,
                'X-RateLimit-Limit'     => (string) $maxAttempts,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset'     => (string) $expiresAt,
            ]);
            return $response;
        }

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->header('X-RateLimit-Limit', (string) $maxAttempts);
            $response->header('X-RateLimit-Remaining', (string) $remaining);
            $response->header('X-RateLimit-Reset', (string) $expiresAt);
        }

        return $response;
    }
}
