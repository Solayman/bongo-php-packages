<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Middleware;

use System\Auth\Auth;
use System\Http\HttpException;

/**
 * Route::get('/admin', [...])->middleware(RoleMiddleware::class . ':admin|manager');   // any of the roles
 * Guests get 401, users without the role 403.
 */
class RoleMiddleware
{
    public function handle(string $role = ''): void
    {
        $user = Auth::user();
        if (!$user) {
            throw new HttpException(401, 'Please sign in.');
        }

        $roles = array_values(array_filter(array_map('trim', explode('|', $role)), 'strlen'));
        if ($roles === [] || !method_exists($user, 'hasAnyRole') || !$user->hasAnyRole($roles)) {
            throw new HttpException(403, 'This action is unauthorized.');
        }
    }
}
