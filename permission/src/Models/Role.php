<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Models;

use Bongo\Packages\Permission\Traits\HasPermissions;
use System\Database\BelongsToMany;
use System\Database\Model;

class Role extends Model
{
    use HasPermissions;

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

    public function hasPermission(string $permission): bool
    {
        return $this->hasDirectPermission($permission);
    }

    public function clearRelationCache(): static
    {
        $this->relations = [];
        return $this;
    }
}
