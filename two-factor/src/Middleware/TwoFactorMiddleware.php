<?php

declare(strict_types=1);

namespace Bongo\Packages\TwoFactor\Middleware;

use System\Auth\Auth;
use System\Database\DB;
use System\Http\Request;

/**
 * Users with 2FA turned on must pass /2fa/challenge before reaching routes with this middleware.
 *
 *   'middleware' => ['2fa' => \Bongo\Packages\TwoFactor\Middleware\TwoFactorMiddleware::class]   (config/app.php)
 *   Route::middleware(['web', 'auth', '2fa'])->group(...);
 */
class TwoFactorMiddleware
{
    public function handle(): void
    {
        $user = Auth::user();
        $userId = $user ? (int) ($user->id ?? 0) : 0;
        if ($userId === 0) {
            return;   // guests are handled by the 'auth' middleware
        }

        // Passed the challenge in this session, as this same user
        if (($_SESSION['2fa_verified'] ?? false) === true && ($_SESSION['2fa_verified_user'] ?? null) === $userId) {
            return;
        }

        $path = '/' . ltrim(Request::path(), '/');
        if (str_starts_with($path, '/2fa/') || $path === '/logout') {
            return;   // the challenge itself, and a way out
        }

        try {
            $enabled = DB::table('two_factor_auth')->where('user_id', $userId)->whereNotNull('confirmed_at')->exists();
        } catch (\Throwable $e) {
            $enabled = false;   // no two_factor_auth table yet: nobody has 2FA on
        }

        if ($enabled) {
            redirect((string) (function_exists('config') ? config('two_factor.challenge_route', '/2fa/challenge') : '/2fa/challenge'));
        }
    }
}
