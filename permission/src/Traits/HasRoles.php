<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Traits;

use Bongo\Packages\Permission\Models\PermissionModel;
use Bongo\Packages\Permission\Models\Role;
use System\Database\BelongsToMany;
use System\Database\DB;

trait HasRoles
{
    use HasPermissions;

    protected ?array $rolePermissionsCache = null;

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

    /**
     * Resolve roles list to array of Role model instances in a single batched query.
     * Prevents N+1 queries when assigning or syncing multiple roles.
     *
     * @param array<string|Role> $roles
     * @return array<Role>
     */
    protected function resolveRoleModels(array $roles, string $guard = 'web'): array
    {
        $models = [];
        $stringNames = [];

        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $models[] = $role;
            } elseif (is_string($role) && trim($role) !== '') {
                $stringNames[] = trim($role);
            }
        }

        if (!empty($stringNames)) {
            $stringNames = array_values(array_unique($stringNames));
            $existing = Role::where('guard_name', $guard)
                ->whereIn('name', $stringNames)
                ->get();

            $existingMap = [];
            foreach ($existing as $model) {
                $existingMap[$model->name] = $model;
            }

            foreach ($stringNames as $name) {
                if (isset($existingMap[$name])) {
                    $models[] = $existingMap[$name];
                } else {
                    $models[] = Role::create([
                        'name' => $name,
                        'guard_name' => $guard,
                    ]);
                }
            }
        }

        return $models;
    }

    public function assignRole(string|Role|array $roles): static
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $guard = $this->guard_name ?? 'web';
        $models = $this->resolveRoleModels($roles, $guard);

        foreach ($models as $roleModel) {
            $this->roles()->attach($roleModel->id);
        }

        $this->relations = [];
        $this->rolePermissionsCache = null;
        return $this;
    }

    public function removeRole(string|Role|array $roles): static
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $guard = $this->guard_name ?? 'web';

        foreach ($roles as $role) {
            $name = is_string($role) ? $role : $role->name;
            $roleModel = Role::findByName($name, $guard);
            if ($roleModel) {
                $this->roles()->detach($roleModel->id);
            }
        }

        $this->relations = [];
        $this->rolePermissionsCache = null;
        return $this;
    }

    public function syncRoles(array $roles): static
    {
        $guard = $this->guard_name ?? 'web';
        $models = $this->resolveRoleModels($roles, $guard);
        $ids = array_map(fn($m) => $m->id, $models);

        $this->roles()->sync($ids);
        $this->relations = [];
        $this->rolePermissionsCache = null;
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

    /**
     * Get all permission names granted to this model via its assigned roles.
     * Batches role ID lookups into a single O(1) query, completely preventing N+1 queries.
     *
     * @return array<string>
     */
    public function getRolePermissions(): array
    {
        if ($this->rolePermissionsCache !== null) {
            return $this->rolePermissionsCache;
        }

        $roles = $this->roles;
        if (!is_array($roles) || empty($roles)) {
            return $this->rolePermissionsCache = [];
        }

        $roleIds = array_values(array_filter(array_map(fn($r) => $r->id ?? null, $roles)));
        if (empty($roleIds)) {
            return $this->rolePermissionsCache = [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $rows = DB::raw(
            "SELECT DISTINCT p.name FROM permissions p " .
            "INNER JOIN role_has_permissions rhp ON rhp.permission_id = p.id " .
            "WHERE rhp.role_id IN ({$placeholders})",
            $roleIds
        );

        $names = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $name = is_array($row) ? ($row['name'] ?? null) : ($row->name ?? null);
                if ($name !== null) {
                    $names[(string) $name] = (string) $name;
                }
            }
        }

        return $this->rolePermissionsCache = array_values($names);
    }

    public function hasPermissionViaRole(string $permission): bool
    {
        return in_array($permission, $this->getRolePermissions(), true);
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

        // Via roles (single batched query, 0 N+1!)
        foreach ($this->getRolePermissions() as $pName) {
            $perms[$pName] = $pName;
        }

        return array_values($perms);
    }

    public function clearRelationCache(): static
    {
        $this->relations = [];
        $this->rolePermissionsCache = null;
        return $this;
    }
}
