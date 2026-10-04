<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup\Archivers;

use Bongo\Packages\Backup\Exceptions\BackupException;
use ZipArchive;

class ZipArchiver
{
    /**
     * @var string[] Default patterns/directories to exclude from file archives
     */
    protected array $excludes = [
        '.git',
        '.svn',
        'node_modules',
        'vendor',
        '.env',
        'cache',
        'sessions',
        'backups',
    ];

    public function __construct(array $excludes = [])
    {
        if (!empty($excludes)) {
            $this->excludes = array_unique(array_merge($this->excludes, $excludes));
        }
    }

    /**
     * Archive given files and directories into a destination ZIP file.
     *
     * @param string $destinationZip Absolute path to destination .zip file.
     * @param array<string, string> $sources Associative array of [source_path => zip_internal_path].
     * @return array{size: int, files_count: int}
     */
    public function archive(string $destinationZip, array $sources): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new BackupException("ZipArchive extension (ext-zip) is required to build ZIP backups.");
        }

        $dir = dirname($destinationZip);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive();
        $status = $zip->open($destinationZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($status !== true) {
            throw new BackupException("Failed to open ZIP archive at [{$destinationZip}], code: {$status}");
        }

        $filesCount = 0;

        foreach ($sources as $sourcePath => $internalPrefix) {
            if (!file_exists($sourcePath)) {
                continue;
            }

            if (is_file($sourcePath)) {
                $entryName = $internalPrefix !== '' ? rtrim($internalPrefix, '/') . '/' . basename($sourcePath) : basename($sourcePath);
                $zip->addFile($sourcePath, $entryName);
                $filesCount++;
            } elseif (is_dir($sourcePath)) {
                $filesCount += $this->addDirectoryToZip($zip, $sourcePath, $internalPrefix);
            }
        }

        $zip->close();

        $size = filesize($destinationZip);
        if ($size === false) {
            throw new BackupException("Failed to determine ZIP archive size: {$destinationZip}");
        }

        return [
            'size' => $size,
            'files_count' => $filesCount,
        ];
    }

    protected function addDirectoryToZip(ZipArchive $zip, string $dir, string $prefix): int
    {
        $count = 0;
        $items = scandir($dir);
        if ($items === false) {
            return 0;
        }

        $prefix = $prefix !== '' ? rtrim($prefix, '/') . '/' : '';

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if ($this->isExcluded($item)) {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
            $zipPath = $prefix . $item;

            if (is_file($fullPath)) {
                $zip->addFile($fullPath, $zipPath);
                $count++;
            } elseif (is_dir($fullPath)) {
                $zip->addEmptyDir($zipPath);
                $count += $this->addDirectoryToZip($zip, $fullPath, $zipPath);
            }
        }

        return $count;
    }

    protected function isExcluded(string $filename): bool
    {
        foreach ($this->excludes as $exclude) {
            if ($filename === $exclude || fnmatch($exclude, $filename)) {
                return true;
            }
        }
        return false;
    }
}
