<?php

declare(strict_types=1);

namespace Bongo\Packages\Zip;

use Bongo\Packages\Zip\Exceptions\ZipException;

class Zip
{
    /**
     * Start building a new ZIP archive.
     */
    public static function create(string $destinationPath): ZipBuilder
    {
        return new ZipBuilder($destinationPath);
    }

    /**
     * Open an existing ZIP archive for reading, inspecting, or extraction.
     */
    public static function open(string $archivePath): ZipReader
    {
        return new ZipReader($archivePath);
    }

    /**
     * Quickly extract an archive to destination directory.
     */
    public static function extract(string $archivePath, string $destinationDir, array|string|null $entries = null): bool
    {
        $reader = static::open($archivePath);
        $reader->extractTo($destinationDir, $entries);
        $reader->close();
        return true;
    }

    /**
     * List all entries inside an archive.
     */
    public static function list(string $archivePath): array
    {
        $reader = static::open($archivePath);
        $list = $reader->list();
        $reader->close();
        return $list;
    }

    /**
     * Read the contents of a specific file inside an archive without extracting the entire archive.
     */
    public static function read(string $archivePath, string $entryName): string
    {
        $reader = static::open($archivePath);
        $content = $reader->read($entryName);
        $reader->close();
        return $content;
    }

    /**
     * Check if a specific file exists inside the archive.
     */
    public static function has(string $archivePath, string $entryName): bool
    {
        $reader = static::open($archivePath);
        $has = $reader->has($entryName);
        $reader->close();
        return $has;
    }

    /**
     * Stream a ZIP file download to the HTTP client with appropriate headers.
     */
    public static function download(string $archivePath, ?string $downloadName = null): void
    {
        if (!file_exists($archivePath)) {
            throw new ZipException("File not found for download: {$archivePath}");
        }

        $name = $downloadName ?? basename($archivePath);
        $name = str_replace(["\r", "\n", '"'], '', $name);
        $size = filesize($archivePath);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($archivePath);
        exit;
    }
}
