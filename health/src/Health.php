<?php

declare(strict_types=1);

namespace Bongo\Packages\Health;

use Bongo\Packages\Health\Checks\Check;

class Health
{
    public static function check(Check|callable $check, ?string $name = null): HealthManager
    {
        return HealthManager::getInstance()->register($check, $name);
    }

    public static function run(): array
    {
        return HealthManager::getInstance()->run();
    }

    public static function manager(): HealthManager
    {
        return HealthManager::getInstance();
    }
}
