<?php

declare(strict_types=1);

namespace Bongo\Packages\Zip;

use Bongo\Packages\Zip\Exceptions\ZipException;
use ZipArchive;

class ZipBuilder
{
    protected ZipArchive $zip;
    protected string $destinationPath;
    protected array $excludes = [];
    protected int $filesCount = 0;
    protected bool $isOpen = false;

    public function __construct(string $destinationPath)
    {
        if (!class_exists(ZipArchive::class)) {
            throw new ZipException("The PHP Zip extension (ext-zip) is required to build ZIP archives.");
        }

        $this->destinationPath = $destinationPath;
        $dir = dirname($destinationPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->zip = new ZipArchive();
        $status = $this->zip->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($status !== true) {
            throw new ZipException("Failed to create ZIP archive at [{$destinationPath}], error code: {$status}");
        }

        $this->isOpen = true;
    }

    public function __destruct()
    {
        if ($this->isOpen) {
            $this->save();
        }
    }

    /**
     * Add a file or directory recursively to the archive.
     */
    public function add(string $path, ?string $internalPath = null): static
    {
        if (!file_exists($path)) {
            throw new ZipException("Source path not found: {$path}");
        }

        if (is_file($path)) {
            return $this->addFile($path, $internalPath);
        }

        if (is_dir($path)) {
            $this->addDirectory($path, $internalPath ?? '');
        }

        return $this;
    }

    /**
     * Add a single file to the archive.
     */
    public function addFile(string $filePath, ?string $entryName = null): static
    {
        if (!file_exists($filePath)) {
            throw new ZipException("File not found: {$filePath}");
        }

        $entryName ??= basename($filePath);
        if (!$this->zip->addFile($filePath, $entryName)) {
            throw new ZipException("Failed to add file [{$filePath}] to archive as [{$entryName}].");
        }

        $this->filesCount++;
        return $this;
    }

    /**
     * Add content from a string directly into the archive.
     */
    public function addFromString(string $entryName, string $contents): static
    {
        if (!$this->zip->addFromString($entryName, $contents)) {
            throw new ZipException("Failed to add content to archive as [{$entryName}].");
        }

        $this->filesCount++;
        return $this;
    }

    /**
     * Add an empty directory entry.
     */
    public function addEmptyDir(string $dirName): static
    {
        $this->zip->addEmptyDir(rtrim($dirName, '/\\'));
        return $this;
    }

    /**
     * Set exclusion patterns (e.g. ['.git', '*.log', 'node_modules']).
     */
    public function exclude(array|string $patterns): static
    {
        $patterns = is_array($patterns) ? $patterns : [$patterns];
        $this->excludes = array_unique(array_merge($this->excludes, $patterns));
        return $this;
    }

    /**
     * Set archive comment.
     */
    public function setComment(string $comment): static
    {
        $this->zip->setArchiveComment($comment);
        return $this;
    }

    /**
     * Set default encryption password for files added to archive.
     */
    public function setPassword(string $password): static
    {
        $this->zip->setPassword($password);
        return $this;
    }

    public function count(): int
    {
        return $this->filesCount;
    }

    /**
     * Close the archive and save to disk. Returns destination path.
     */
    public function save(): string
    {
        if ($this->isOpen) {
            $this->zip->close();
            $this->isOpen = false;
        }

        return $this->destinationPath;
    }

    public function close(): string
    {
        return $this->save();
    }

    protected function addDirectory(string $dir, string $prefix): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
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
                $this->zip->addFile($fullPath, $zipPath);
                $this->filesCount++;
            } elseif (is_dir($fullPath)) {
                $this->zip->addEmptyDir($zipPath);
                $this->addDirectory($fullPath, $zipPath);
            }
        }
    }

    protected function isExcluded(string $name): bool
    {
        foreach ($this->excludes as $pattern) {
            if ($name === $pattern || fnmatch($pattern, $name)) {
                return true;
            }
        }
        return false;
    }
}
