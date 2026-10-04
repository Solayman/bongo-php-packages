<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Commands;

use Bongo\Packages\Permission\Models\Role;
use System\Console\Command;

class RoleAssignCommand extends Command
{
    protected string $name = 'role:assign';
    protected string $description = 'Assign a role to a user by ID';

    public function handle(array $args, array $options): int
    {
        $userId = $args[0] ?? null;
        $roleName = $args[1] ?? null;

        if (!$userId || !$roleName) {
            $this->error("Usage: php bongo role:assign <user_id> <role_name>");
            return 1;
        }

        $userModel = function_exists('config') ? config('permission.user_model', 'App\\Models\\User') : 'App\\Models\\User';
        if (!class_exists($userModel)) {
            $this->error("User model [{$userModel}] does not exist.");
            return 1;
        }

        $user = $userModel::find($userId);
        if (!$user) {
            $this->error("User with ID [{$userId}] not found.");
            return 1;
        }

        if (!method_exists($user, 'assignRole')) {
            $this->error("User model [{$userModel}] does not use the HasRoles trait.");
            return 1;
        }

        $role = Role::findOrCreate($roleName);
        $user->assignRole($role);

        $this->info("Assigned role [{$roleName}] to user ID {$userId} successfully.");
        return 0;
    }
}
