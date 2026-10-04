<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Models;

use System\Database\BelongsToMany;
use System\Database\Model;

class Role extends Model
{
    protected static $table = 'roles';
    protected array $fillable = ['name', 'guard_name'];

    public static function findByName(string $name, string $guard = 'web'): ?static
    {
        return static::where('name', $name)->where('guard_name', $guard)->first();
    }

    public static function findOrCreate(string $name, string $guard = 'web'): static
    {
        $existing = static::findByName($name, $guard);
        if ($existing) {
            return $existing;
        }

        return static::create([
            'name' => $name,
            'guard_name' => $guard,
        ]);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            PermissionModel::class,
            'role_has_permissions',
            'role_id',
            'permission_id'
        );
    }

    public function givePermissionTo(string|PermissionModel|array $permissions): static
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        foreach ($permissions as $perm) {
            $permModel = is_string($perm)
                ? PermissionModel::findOrCreate($perm, $this->guard_name ?? 'web')
                : $perm;

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
            $permModel = PermissionModel::findByName($name, $this->guard_name ?? 'web');
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
            $permModel = is_string($perm)
                ? PermissionModel::findOrCreate($perm, $this->guard_name ?? 'web')
                : $perm;
            if ($permModel) {
                $ids[] = $permModel->id;
            }
        }

        $this->permissions()->sync($ids);
        $this->relations = [];
        return $this;
    }

    public function hasPermission(string $permission): bool
    {
        $list = $this->permissions;
        if (is_array($list)) {
            foreach ($list as $p) {
                if ($p->name === $permission) {
                    return true;
                }
            }
        }

        return false;
    }

    public function clearRelationCache(): static
    {
        $this->relations = [];
        return $this;
    }
}
