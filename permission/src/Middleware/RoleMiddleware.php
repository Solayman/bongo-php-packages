<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Middleware;

use Bongo\Packages\Permission\Exceptions\UnauthorizedException;
use Closure;
use System\Auth\Auth;
use System\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        $user = Auth::user() ?? (method_exists($request, 'user') ? $request->user() : null) ?? ($request->user ?? null);
        if (!$user) {
            throw UnauthorizedException::notLoggedIn();
        }

        $roles = explode('|', $role);
        if (!method_exists($user, 'hasAnyRole') || !$user->hasAnyRole($roles)) {
            throw UnauthorizedException::forRoles($roles);
        }

        return $next($request);
    }
}
