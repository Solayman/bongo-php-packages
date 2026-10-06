<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Middleware;

use System\Auth\Auth;
use System\Http\HttpException;

/**
 * Route::post('/articles', [...])->middleware(PermissionMiddleware::class . ':create-articles|publish-articles');
 * Any one of the permissions is enough. Guests get 401, users without the permission 403.
 */
class PermissionMiddleware
{
    public function handle(string $permission = ''): void
    {
        $user = Auth::user();
        if (!$user) {
            throw new HttpException(401, 'Please sign in.');
        }

        foreach (array_filter(array_map('trim', explode('|', $permission)), 'strlen') as $perm) {
            if (method_exists($user, 'can') && $user->can($perm)) {
                return;
            }
        }

        throw new HttpException(403, 'This action is unauthorized.');
    }
}
