<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Models;

use System\Database\BelongsToMany;
use System\Database\Model;

class PermissionModel extends Model
{
    protected static $table = 'permissions';
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_has_permissions',
            'permission_id',
            'role_id'
        );
    }
}
