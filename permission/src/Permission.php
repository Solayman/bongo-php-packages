<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission;

use Bongo\Packages\Permission\Models\PermissionModel;
use Bongo\Packages\Permission\Models\Role;

class Permission
{
    public static function createRole(string $name, string $guard = 'web'): Role
    {
        return Role::findOrCreate($name, $guard);
    }

    public static function createPermission(string $name, string $guard = 'web'): PermissionModel
    {
        return PermissionModel::findOrCreate($name, $guard);
    }

    public static function findRole(string $name, string $guard = 'web'): ?Role
    {
        return Role::findByName($name, $guard);
    }

    public static function findPermission(string $name, string $guard = 'web'): ?PermissionModel
    {
        return PermissionModel::findByName($name, $guard);
    }

    public static function userCan(mixed $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'can')) {
            return $user->can($permission);
        }

        return false;
    }

    public static function userHasRole(mixed $user, string|array $roles): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($roles);
        }

        return false;
    }
}
