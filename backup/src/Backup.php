<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup;

class Backup
{
    protected static ?BackupManager $manager = null;

    public static function manager(): BackupManager
    {
        return static::$manager ??= new BackupManager();
    }

    public static function setManager(?BackupManager $manager): void
    {
        static::$manager = $manager;
    }

    public static function run(array $options = []): BackupResult
    {
        return static::manager()->run($options);
    }

    public static function list(?string $disk = null): array
    {
        return static::manager()->list($disk);
    }

    public static function clean(?string $disk = null, ?int $keepCount = null, ?int $keepDays = null): int
    {
        return static::manager()->clean($disk, $keepCount, $keepDays);
    }
}
