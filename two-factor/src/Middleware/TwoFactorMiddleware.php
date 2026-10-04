<?php

declare(strict_types=1);

namespace Bongo\Packages\TwoFactor\Middleware;

use Closure;
use System\Auth\Auth;
use System\Database\DB;
use System\Http\Request;
use System\Http\Response;

class TwoFactorMiddleware
{
    /**
     * Handle incoming request and verify 2FA status if enabled for the user.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();
        if (!$user) {
            return $next($request);
        }

        // Check if user has active 2FA record
        $userId = $user->id ?? $user->getId() ?? null;
        if (!$userId || !class_exists(DB::class)) {
            return $next($request);
        }

        try {
            $record = DB::table('two_factor_auth')
                ->where('user_id', $userId)
                ->whereNotNull('confirmed_at')
                ->first();

            if ($record) {
                $sessionVerified = function_exists('session') && session('2fa_verified') === true;
                $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

                // Allow 2FA challenge and verify routes
                if (!$sessionVerified && !str_starts_with($currentPath, '/2fa/challenge') && !str_starts_with($currentPath, '/2fa/verify')) {
                    $challengeUrl = function_exists('config') ? config('two_factor.challenge_route', '/2fa/challenge') : '/2fa/challenge';
                    return redirect($challengeUrl);
                }
            }
        } catch (\Throwable $e) {
            // Silently allow if table does not exist or database error
        }

        return $next($request);
    }
}
