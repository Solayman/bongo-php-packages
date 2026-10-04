<?php

declare(strict_types=1);

namespace Bongo\Packages\Settings;

class Setting
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return SettingsManager::getInstance()->get($key, $default);
    }

    public static function set(
        string|array $key,
        mixed $value = null,
        ?string $type = null,
        ?string $group = null
    ): void {
        SettingsManager::getInstance()->set($key, $value, $type, $group);
    }

    public static function has(string $key): bool
    {
        return SettingsManager::getInstance()->has($key);
    }

    public static function forget(string $key): bool
    {
        return SettingsManager::getInstance()->forget($key);
    }

    public static function all(?string $group = null): array
    {
        return SettingsManager::getInstance()->all($group);
    }

    public static function clearCache(): void
    {
        SettingsManager::getInstance()->clearCache();
    }
}
