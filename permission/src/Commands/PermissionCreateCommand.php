<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Commands;

use Bongo\Packages\Permission\Models\PermissionModel;
use System\Console\Command;

class PermissionCreateCommand extends Command
{
    protected string $name = 'permission:create';
    protected string $description = 'Create a new permission';

    public function handle(array $args, array $options): int
    {
        $permName = $args[0] ?? null;
        if (!$permName) {
            $this->error("Usage: php bongo permission:create <name> [--guard=web]");
            return 1;
        }

        $guard = $options['guard'] ?? 'web';

        $perm = PermissionModel::findOrCreate($permName, $guard);
        $this->info("Permission [{$perm->name}] (guard: {$perm->guard_name}, ID: {$perm->id}) created successfully.");

        return 0;
    }
}
