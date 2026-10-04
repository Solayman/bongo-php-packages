<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Traits;

use Bongo\Packages\Permission\Models\PermissionModel;

trait HasPermissions
{
    /**
     * Resolve permissions list to array of PermissionModel instances in a single batched query.
     * Prevents N+1 SELECT queries when passing an array of permission names.
     *
     * @param array<string|PermissionModel> $permissions
     * @return array<PermissionModel>
     */
    protected function resolvePermissionModels(array $permissions, string $guard = 'web'): array
    {
        $models = [];
        $stringNames = [];

        foreach ($permissions as $perm) {
            if ($perm instanceof PermissionModel) {
                $models[] = $perm;
            } elseif (is_string($perm) && trim($perm) !== '') {
                $stringNames[] = trim($perm);
            }
        }

        if (!empty($stringNames)) {
            $stringNames = array_values(array_unique($stringNames));
            $existing = PermissionModel::where('guard_name', $guard)
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
                    $models[] = PermissionModel::create([
                        'name' => $name,
                        'guard_name' => $guard,
                    ]);
                }
            }
        }

        return $models;
    }

    /**
     * Grant permission(s) to this model.
     */
    public function givePermissionTo(string|PermissionModel|array $permissions): static
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $guard = $this->guard_name ?? 'web';
        $models = $this->resolvePermissionModels($permissions, $guard);

        foreach ($models as $permModel) {
            $this->permissions()->attach($permModel->id);
        }

        $this->relations = [];
        if (property_exists($this, 'rolePermissionsCache')) {
            $this->rolePermissionsCache = null;
        }

        return $this;
    }

    /**
     * Revoke permission(s) from this model.
     */
    public function revokePermissionTo(string|PermissionModel|array $permissions): static
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $guard = $this->guard_name ?? 'web';

        foreach ($permissions as $perm) {
            $name = is_string($perm) ? $perm : $perm->name;
            $permModel = PermissionModel::findByName($name, $guard);
            if ($permModel) {
                $this->permissions()->detach($permModel->id);
            }
        }

        $this->relations = [];
        if (property_exists($this, 'rolePermissionsCache')) {
            $this->rolePermissionsCache = null;
        }

        return $this;
    }

    /**
     * Synchronize permissions, detaching missing ones and attaching new ones.
     */
    public function syncPermissions(array $permissions): static
    {
        $guard = $this->guard_name ?? 'web';
        $models = $this->resolvePermissionModels($permissions, $guard);
        $ids = array_map(fn($m) => $m->id, $models);

        $this->permissions()->sync($ids);

        $this->relations = [];
        if (property_exists($this, 'rolePermissionsCache')) {
            $this->rolePermissionsCache = null;
        }

        return $this;
    }

    /**
     * Check if model has direct permission.
     */
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
}
