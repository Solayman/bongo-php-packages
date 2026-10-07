<?php

declare(strict_types=1);

namespace Bongo\Packages\Zip;

use Bongo\Packages\Zip\Exceptions\ZipException;
use ZipArchive;

class ZipReader
{
    protected ZipArchive $zip;
    protected string $archivePath;
    protected bool $isOpen = false;

    public function __construct(string $archivePath)
    {
        if (!class_exists(ZipArchive::class)) {
            throw new ZipException("The PHP Zip extension (ext-zip) is required to read ZIP archives.");
        }

        if (!file_exists($archivePath)) {
            throw new ZipException("ZIP archive file not found: {$archivePath}");
        }

        $this->archivePath = $archivePath;
        $this->zip = new ZipArchive();
        $status = $this->zip->open($archivePath);
        if ($status !== true) {
            throw new ZipException("Failed to open ZIP archive [{$archivePath}], error code: {$status}");
        }

        $this->isOpen = true;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Extract all or specific entries to a destination directory.
     * Includes strict protection against Zip-Slip path traversal vulnerabilities.
     *
     * @param string $destinationDir
     * @param array|string|null $entries
     * @return static
     */
    public function extractTo(string $destinationDir, array|string|null $entries = null): static
    {
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $realDest = realpath($destinationDir);
        if ($realDest === false) {
            throw new ZipException("Invalid destination directory: {$destinationDir}");
        }

        $filter = $entries !== null ? (is_array($entries) ? $entries : [$entries]) : null;

        // Zip bomb limits: a small archive can expand to fill the disk. Counted on the bytes actually
        // written (the sizes declared inside an archive can lie).
        $maxEntries = (int) (function_exists('config') ? config('zip.max_entries', 10000) : 10000);
        $remaining = (int) (function_exists('config') ? config('zip.max_extract_bytes', 1073741824) : 1073741824);
        if ($maxEntries > 0 && $this->zip->numFiles > $maxEntries) {
            throw new ZipException("The archive has {$this->zip->numFiles} entries; the limit is {$maxEntries}.");
        }

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $name = $stat['name'];

            if ($filter !== null && !in_array($name, $filter, true)) {
                continue;
            }

            // Path traversal defense (Zip Slip protection)
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\') || str_contains($name, "\0")) {
                throw new ZipException("Path traversal attempt detected in ZIP entry: {$name}");
            }

            $targetPath = $realDest . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $name);

            if (str_ends_with($name, '/') || str_ends_with($name, '\\')) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $dir = dirname($targetPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $realTargetDir = realpath($dir);
                if ($realTargetDir !== false && !str_starts_with($realTargetDir, $realDest)) {
                    throw new ZipException("Path traversal attempt detected in ZIP entry: {$name}");
                }

                $stream = $this->zip->getStream($name);
                if ($stream === false) {
                    throw new ZipException("Failed to read stream for entry: {$name}");
                }

                $out = fopen($targetPath, 'wb');
                if ($out === false) {
                    fclose($stream);
                    throw new ZipException("Failed to write extracted file: {$targetPath}");
                }

                $written = stream_copy_to_stream($stream, $out, $remaining > 0 ? $remaining + 1 : null);
                fclose($stream);
                fclose($out);
                if ($remaining > 0) {
                    $remaining -= (int) $written;
                    if ($remaining < 0) {
                        @unlink($targetPath);
                        throw new ZipException('The archive expands beyond the extraction limit (zip.max_extract_bytes).');
                    }
                }
            }
        }

        return $this;
    }

    /**
     * List all entries inside the archive.
     *
     * @return array<int, array{name: string, size: int, compressed_size: int, mtime: int, is_dir: bool, crc: int}>
     */
    public function list(): array
    {
        $list = [];

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $name = $stat['name'];
            $isDir = str_ends_with($name, '/') || str_ends_with($name, '\\');

            $list[] = [
                'name' => $name,
                'size' => (int) ($stat['size'] ?? 0),
                'compressed_size' => (int) ($stat['comp_size'] ?? 0),
                'mtime' => (int) ($stat['mtime'] ?? 0),
                'date' => date('Y-m-d H:i:s', (int) ($stat['mtime'] ?? time())),
                'is_dir' => $isDir,
                'crc' => (int) ($stat['crc'] ?? 0),
            ];
        }

        return $list;
    }

    /**
     * Check if a specific entry exists in the archive.
     */
    public function has(string $entryName): bool
    {
        return $this->zip->locateName($entryName) !== false;
    }

    /**
     * Read the binary content of a single entry from the archive.
     */
    public function read(string $entryName): string
    {
        $content = $this->zip->getFromName($entryName);
        if ($content === false) {
            throw new ZipException("Failed to read entry [{$entryName}] from archive.");
        }

        return $content;
    }

    /**
     * Delete one or more entries from the archive.
     */
    public function delete(string|array $entries): static
    {
        $entries = is_array($entries) ? $entries : [$entries];

        foreach ($entries as $entry) {
            if (!$this->zip->deleteName($entry)) {
                throw new ZipException("Failed to delete entry [{$entry}] from archive.");
            }
        }

        return $this;
    }

    public function count(): int
    {
        return $this->zip->numFiles;
    }

    public function close(): void
    {
        if ($this->isOpen) {
            $this->zip->close();
            $this->isOpen = false;
        }
    }
}
