<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Middleware;

use Bongo\Packages\ApiTools\ApiResponse;
use System\Cache\RateLimiter;
use System\Http\Middleware\ThrottleRequests;
use System\Http\Request;

/**
 * Rate limiting with api-tools' JSON error format (code RATE_LIMIT_EXCEEDED).
 *
 *   Route::middleware(ApiThrottleMiddleware::class . ':60,1')->group(...);   // 60 requests per minute
 *
 * Counts per signed-in user (or per IP) and per route, with the core's atomic counter, so
 * parallel requests cannot slip past the limit. Sends X-RateLimit-* headers on every answer.
 */
class ApiThrottleMiddleware extends ThrottleRequests
{
    public function handle(string $maxAttempts = '60', string $decayMinutes = '1'): void
    {
        $max = max(1, (int) $maxAttempts);
        $decay = max(1, (int) round((float) $decayMinutes * 60));
        $limiter = new RateLimiter();
        $key = 'api:' . $this->key();

        $hits = $limiter->hit($key, $decay);
        $reset = time() + max(1, $limiter->availableIn($key));

        if ($hits > $max) {
            $this->reject([
                'Retry-After'           => (string) max(1, $reset - time()),
                'X-RateLimit-Limit'     => (string) $max,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset'     => (string) $reset,
            ]);
            return;
        }

        if (!headers_sent()) {
            header("X-RateLimit-Limit: $max");
            header('X-RateLimit-Remaining: ' . max(0, $max - $hits));
            header("X-RateLimit-Reset: $reset");
        }
    }

    /** Answer 429 and stop the request */
    protected function reject(array $headers): void
    {
        ApiResponse::error('Too Many Requests', 429, [], 'RATE_LIMIT_EXCEEDED', $headers)->send();
        exit;
    }
}
