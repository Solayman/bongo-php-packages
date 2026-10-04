<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Commands;

use Bongo\Packages\Permission\Models\Role;
use System\Console\Command;

class RoleCreateCommand extends Command
{
    protected string $name = 'role:create';
    protected string $description = 'Create a new security role';

    public function handle(array $args, array $options): int
    {
        $roleName = $args[0] ?? null;
        if (!$roleName) {
            $this->error("Usage: php bongo role:create <name> [--guard=web]");
            return 1;
        }

        $guard = $options['guard'] ?? 'web';

        $role = Role::findOrCreate($roleName, $guard);
        $this->info("Role [{$role->name}] (guard: {$role->guard_name}, ID: {$role->id}) created successfully.");

        return 0;
    }
}
