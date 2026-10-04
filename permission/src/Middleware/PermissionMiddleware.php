<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Middleware;

use Bongo\Packages\Permission\Exceptions\UnauthorizedException;
use Closure;
use System\Auth\Auth;
use System\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = Auth::user() ?? (method_exists($request, 'user') ? $request->user() : null) ?? ($request->user ?? null);
        if (!$user) {
            throw UnauthorizedException::notLoggedIn();
        }

        $permissions = explode('|', $permission);
        $hasPermission = false;

        foreach ($permissions as $perm) {
            if (method_exists($user, 'can') && $user->can($perm)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            throw UnauthorizedException::forPermissions($permissions);
        }

        return $next($request);
    }
}
