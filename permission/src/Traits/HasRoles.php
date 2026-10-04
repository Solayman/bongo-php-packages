<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Traits;

use Bongo\Packages\Permission\Models\PermissionModel;
use Bongo\Packages\Permission\Models\Role;
use System\Database\BelongsToMany;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'model_has_roles',
            'model_id',
            'role_id'
        );
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            PermissionModel::class,
            'model_has_permissions',
            'model_id',
            'permission_id'
        );
    }

    public function assignRole(string|Role|array $roles): static
    {
        $roles = is_array($roles) ? $roles : [$roles];

        foreach ($roles as $role) {
            $roleModel = is_string($role) ? Role::findOrCreate($role) : $role;
            if ($roleModel) {
                $this->roles()->attach($roleModel->id);
            }
        }

        $this->relations = [];
        return $this;
    }

    public function removeRole(string|Role|array $roles): static
    {
        $roles = is_array($roles) ? $roles : [$roles];

        foreach ($roles as $role) {
            $name = is_string($role) ? $role : $role->name;
            $roleModel = Role::findByName($name);
            if ($roleModel) {
                $this->roles()->detach($roleModel->id);
            }
        }

        $this->relations = [];
        return $this;
    }

    public function syncRoles(array $roles): static
    {
        $ids = [];
        foreach ($roles as $role) {
            $roleModel = is_string($role) ? Role::findOrCreate($role) : $role;
            if ($roleModel) {
                $ids[] = $roleModel->id;
            }
        }

        $this->roles()->sync($ids);
        $this->relations = [];
        return $this;
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $userRoles = $this->getRoleNames();

        foreach ($roles as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyRole(string|array $roles): bool
    {
        return $this->hasRole($roles);
    }

    public function hasAllRoles(array $roles): bool
    {
        $userRoles = $this->getRoleNames();

        foreach ($roles as $role) {
            if (!in_array($role, $userRoles, true)) {
                return false;
            }
        }

        return true;
    }

    public function getRoleNames(): array
    {
        $roles = $this->roles;
        if (!is_array($roles)) {
            return [];
        }

        return array_map(fn($r) => $r->name, $roles);
    }

    public function givePermissionTo(string|PermissionModel|array $permissions): static
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        foreach ($permissions as $perm) {
            $permModel = is_string($perm) ? PermissionModel::findOrCreate($perm) : $perm;
            if ($permModel) {
                $this->permissions()->attach($permModel->id);
            }
        }

        $this->relations = [];
        return $this;
    }

    public function revokePermissionTo(string|PermissionModel|array $permissions): static
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        foreach ($permissions as $perm) {
            $name = is_string($perm) ? $perm : $perm->name;
            $permModel = PermissionModel::findByName($name);
            if ($permModel) {
                $this->permissions()->detach($permModel->id);
            }
        }

        $this->relations = [];
        return $this;
    }

    public function syncPermissions(array $permissions): static
    {
        $ids = [];
        foreach ($permissions as $perm) {
            $permModel = is_string($perm) ? PermissionModel::findOrCreate($perm) : $perm;
            if ($permModel) {
                $ids[] = $permModel->id;
            }
        }

        $this->permissions()->sync($ids);
        $this->relations = [];
        return $this;
    }

    public function hasDirectPermission(string $permission): bool
    {
        $directPerms = $this->permissions;
        if (!is_array($directPerms)) {
            return false;
        }

        foreach ($directPerms as $p) {
            if ($p->name === $permission) {
                return true;
            }
        }

        return false;
    }

    public function hasPermissionViaRole(string $permission): bool
    {
        $roles = $this->roles;
        if (!is_array($roles)) {
            return false;
        }

        foreach ($roles as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        // Super-admin bypass check
        if ($this->hasRole('super-admin')) {
            return true;
        }

        return $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission);
    }

    public function can(string $permission): bool
    {
        return $this->hasPermission($permission);
    }

    public function getAllPermissions(): array
    {
        $perms = [];

        // Direct
        if (is_array($this->permissions)) {
            foreach ($this->permissions as $p) {
                $perms[$p->name] = $p->name;
            }
        }

        // Via roles
        if (is_array($this->roles)) {
            foreach ($this->roles as $role) {
                if (is_array($role->permissions)) {
                    foreach ($role->permissions as $p) {
                        $perms[$p->name] = $p->name;
                    }
                }
            }
        }

        return array_values($perms);
    }

    public function clearRelationCache(): static
    {
        $this->relations = [];
        return $this;
    }
}
