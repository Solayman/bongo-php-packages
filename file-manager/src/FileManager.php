<?php

declare(strict_types=1);

namespace Bongo\Packages\FileManager;

use Bongo\Packages\FileManager\Services\FileManagerService;

class FileManager
{
    protected static ?FileManagerService $service = null;

    public static function service(): FileManagerService
    {
        if (self::$service === null) {
            self::$service = new FileManagerService();
        }

        return self::$service;
    }

    public static function setService(?FileManagerService $service): void
    {
        self::$service = $service;
    }

    public static function getItems(string $subDir = ''): array
    {
        return self::service()->getItems($subDir);
    }

    public static function getTree(string $subDir = ''): array
    {
        return self::service()->getTree($subDir);
    }

    public static function createFolder(string $parentDir, string $folderName): bool
    {
        return self::service()->createFolder($parentDir, $folderName);
    }

    public static function deleteItem(string $path): bool
    {
        return self::service()->deleteItem($path);
    }

    public static function renameItem(string $oldPath, string $newName): bool
    {
        return self::service()->renameItem($oldPath, $newName);
    }

    public static function moveItem(string $sourcePath, string $destFolder): bool
    {
        return self::service()->moveItem($sourcePath, $destFolder);
    }

    public static function readFile(string $path): string
    {
        return self::service()->readFile($path);
    }

    public static function saveFile(string $path, string $content): bool
    {
        return self::service()->saveFile($path, $content);
    }
}
