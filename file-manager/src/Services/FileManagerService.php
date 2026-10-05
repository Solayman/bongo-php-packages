<?php

declare(strict_types=1);

namespace Bongo\Packages\FileManager\Services;

use System\Storage\Storage;

class FileManagerService
{
    protected string $disk;
    protected string $baseDir;

    public function __construct(?string $disk = null, ?string $baseDir = null)
    {
        $this->disk = $disk ?? (function_exists('config') ? (string) config('filemanager.disk', 'public') : 'public');
        $this->baseDir = $baseDir ?? (function_exists('config') ? (string) config('filemanager.base_directory', 'uploads') : 'uploads');
        $this->ensureBaseDirectory();
    }

    protected function getStorageDisk()
    {
        return Storage::disk($this->disk);
    }

    public function resolveFullPath(string $subPath = ''): string
    {
        $cleanSub = PathSecurity::sanitize($subPath);
        $combined = trim($this->baseDir . '/' . $cleanSub, '/');

        // If local/public disk, get real directory path
        if (method_exists($this->getStorageDisk(), 'path')) {
            return $this->getStorageDisk()->path($combined);
        }

        $base = function_exists('storage_path') ? storage_path('app/' . $combined) : $combined;
        return $base;
    }

    protected function ensureBaseDirectory(): void
    {
        $root = $this->resolveFullPath('');
        if (!is_dir($root)) {
            @mkdir($root, 0755, true);
        }
    }

    /**
     * List all folders and files inside a directory.
     *
     * @return array{folders: array<FileItem>, files: array<FileItem>}
     */
    public function getItems(string $subDir = ''): array
    {
        $cleanSub = PathSecurity::sanitize($subDir);
        $fullDir = $this->resolveFullPath($cleanSub);

        if (!is_dir($fullDir)) {
            return ['folders' => [], 'files' => []];
        }

        $entries = scandir($fullDir);
        if ($entries === false) {
            return ['folders' => [], 'files' => []];
        }

        $folders = [];
        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryFullPath = $fullDir . '/' . $entry;
            $relativeItemPath = trim($cleanSub . '/' . $entry, '/');

            if (is_dir($entryFullPath)) {
                $folders[] = new FileItem(
                    name: $entry,
                    path: $relativeItemPath,
                    isDir: true,
                    size: 0,
                    type: 'folder',
                    updatedAt: date('Y-m-d H:i:s', filemtime($entryFullPath) ?: time())
                );
            } elseif (is_file($entryFullPath)) {
                $size = (int) filesize($entryFullPath);
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                $type = 'file';

                if (PathSecurity::isImage($entry)) {
                    $type = 'image';
                } elseif (PathSecurity::isEditable($entry)) {
                    $type = 'code';
                } elseif ($ext === 'pdf') {
                    $type = 'pdf';
                } elseif (in_array($ext, ['zip', 'tar', 'gz', 'rar'], true)) {
                    $type = 'archive';
                }

                $storageRelative = trim($this->baseDir . '/' . $relativeItemPath, '/');
                $url = method_exists($this->getStorageDisk(), 'url')
                    ? $this->getStorageDisk()->url($storageRelative)
                    : '/' . $storageRelative;

                $prefix = function_exists('config') ? (string) config('filemanager.route_prefix', 'filemanager') : 'filemanager';
                $thumbUrl = $type === 'image' ? "/{$prefix}/thumb?path=" . rawurlencode($relativeItemPath) : null;

                $files[] = new FileItem(
                    name: $entry,
                    path: $relativeItemPath,
                    isDir: false,
                    size: $size,
                    type: $type,
                    url: $url,
                    thumbUrl: $thumbUrl,
                    extension: $ext,
                    updatedAt: date('Y-m-d H:i:s', filemtime($entryFullPath) ?: time()),
                    editorLanguage: PathSecurity::getEditorLanguage($entry)
                );
            }
        }

        // Sort alphabetically
        usort($folders, fn(FileItem $a, FileItem $b) => strcasecmp($a->name, $b->name));
        usort($files, fn(FileItem $a, FileItem $b) => strcasecmp($a->name, $b->name));

        return [
            'folders' => $folders,
            'files' => $files,
        ];
    }

    /**
     * Get recursive folder hierarchy tree.
     */
    public function getTree(string $subDir = ''): array
    {
        $cleanSub = PathSecurity::sanitize($subDir);
        $fullDir = $this->resolveFullPath($cleanSub);

        if (!is_dir($fullDir)) {
            return [];
        }

        $items = scandir($fullDir) ?: [];
        $children = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $fullDir . '/' . $item;
            if (is_dir($path)) {
                $rel = trim($cleanSub . '/' . $item, '/');
                $children[] = [
                    'name' => $item,
                    'path' => $rel,
                    'children' => $this->getTree($rel),
                ];
            }
        }

        return $children;
    }

    /**
     * Create a new subfolder.
     */
    public function createFolder(string $parentDir, string $folderName): bool
    {
        $cleanParent = PathSecurity::sanitize($parentDir);
        $cleanName = basename(PathSecurity::sanitize($folderName));
        $cleanName = preg_replace('/[^a-zA-Z0-9_\. -]/', '_', $cleanName);
        $cleanName = trim($cleanName);

        if ($cleanName === '' || str_starts_with($cleanName, '.')) {
            return false;
        }

        $targetDir = $this->resolveFullPath(trim($cleanParent . '/' . $cleanName, '/'));
        if (file_exists($targetDir)) {
            return false;
        }

        return @mkdir($targetDir, 0755, true);
    }

    /**
     * Delete a file or directory.
     */
    public function deleteItem(string $path): bool
    {
        $cleanPath = PathSecurity::sanitize($path);
        if ($cleanPath === '') {
            return false;
        }

        $target = $this->resolveFullPath($cleanPath);
        $root = $this->resolveFullPath('');
        if ($target === $root || !file_exists($target)) {
            return false;
        }

        $realTarget = realpath($target);
        $realRoot = realpath($root);
        if ($realTarget === false || $realRoot === false || $realTarget === $realRoot || !str_starts_with(str_replace('\\', '/', $realTarget), str_replace('\\', '/', $realRoot))) {
            return false;
        }

        if (is_dir($target)) {
            return $this->deleteDirectoryRecursive($target);
        }

        return @unlink($target);
    }

    protected function deleteDirectoryRecursive(string $dir): bool
    {
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $item = $dir . '/' . $file;
            is_dir($item) ? $this->deleteDirectoryRecursive($item) : @unlink($item);
        }
        return @rmdir($dir);
    }

    /**
     * Rename a file or directory.
     */
    public function renameItem(string $oldPath, string $newName): bool
    {
        $cleanOld = PathSecurity::sanitize($oldPath);
        $cleanName = basename(PathSecurity::sanitize($newName));
        $cleanName = preg_replace('/[^a-zA-Z0-9_\. -]/', '_', $cleanName);
        $cleanName = trim($cleanName);

        if ($cleanOld === '' || $cleanName === '' || str_starts_with($cleanName, '.')) {
            return false;
        }

        $source = $this->resolveFullPath($cleanOld);
        if (!file_exists($source)) {
            return false;
        }

        $realSource = realpath($source);
        $realParent = realpath(dirname($source));
        $realRoot = realpath($this->resolveFullPath(''));
        if ($realSource === false || $realParent === false || $realRoot === false) {
            return false;
        }
        $normSrc = str_replace('\\', '/', $realSource);
        $normParent = str_replace('\\', '/', $realParent);
        $normRoot = str_replace('\\', '/', $realRoot);
        if (!str_starts_with($normSrc, $normRoot) || !str_starts_with($normParent, $normRoot)) {
            return false;
        }

        if (is_file($source)) {
            $oldExt = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $newExt = strtolower(pathinfo($cleanName, PATHINFO_EXTENSION));

            if (!PathSecurity::isAllowedForUpload($cleanName)) {
                if ($oldExt === '' || $oldExt !== $newExt || !PathSecurity::isEditable($cleanName)) {
                    return false;
                }
            }
        }

        $parent = dirname($source);
        $destination = $parent . '/' . $cleanName;

        if (file_exists($destination)) {
            return false;
        }

        return @rename($source, $destination);
    }

    /**
     * Move a file or directory to another destination folder.
     */
    public function moveItem(string $sourcePath, string $destFolder): bool
    {
        $cleanSrc = PathSecurity::sanitize($sourcePath);
        $cleanDst = PathSecurity::sanitize($destFolder);

        if ($cleanSrc === '') {
            return false;
        }

        $source = $this->resolveFullPath($cleanSrc);
        if (!file_exists($source)) {
            return false;
        }

        $targetDir = $this->resolveFullPath($cleanDst);
        if (!is_dir($targetDir)) {
            return false;
        }

        $realSource = realpath($source);
        $realTargetDir = realpath($targetDir);
        $realRoot = realpath($this->resolveFullPath(''));
        if ($realSource === false || $realTargetDir === false || $realRoot === false) {
            return false;
        }
        $normSrc = str_replace('\\', '/', $realSource);
        $normDst = str_replace('\\', '/', $realTargetDir);
        $normRoot = str_replace('\\', '/', $realRoot);
        if (!str_starts_with($normSrc, $normRoot) || !str_starts_with($normDst, $normRoot)) {
            return false;
        }

        $itemName = basename($source);
        $destination = $targetDir . '/' . $itemName;

        if (file_exists($destination)) {
            return false;
        }

        return @rename($source, $destination);
    }

    /**
     * Copy a file or directory.
     */
    public function copyItem(string $sourcePath, string $destFolder, ?string $newName = null): bool
    {
        $cleanSrc = PathSecurity::sanitize($sourcePath);
        $cleanDst = PathSecurity::sanitize($destFolder);

        if ($cleanSrc === '') {
            return false;
        }

        $source = $this->resolveFullPath($cleanSrc);
        if (!is_file($source)) {
            return false;
        }

        $targetDir = $this->resolveFullPath($cleanDst);
        if (!is_dir($targetDir)) {
            return false;
        }

        $realSource = realpath($source);
        $realTargetDir = realpath($targetDir);
        $realRoot = realpath($this->resolveFullPath(''));
        if ($realSource === false || $realTargetDir === false || $realRoot === false) {
            return false;
        }
        $normSrc = str_replace('\\', '/', $realSource);
        $normDst = str_replace('\\', '/', $realTargetDir);
        $normRoot = str_replace('\\', '/', $realRoot);
        if (!str_starts_with($normSrc, $normRoot) || !str_starts_with($normDst, $normRoot)) {
            return false;
        }

        $customName = $newName !== null ? basename(trim(PathSecurity::sanitize($newName))) : '';
        if ($customName !== '') {
            $customName = preg_replace('/[^a-zA-Z0-9_\. -]/', '_', $customName);
            $customName = trim($customName);
            if ($customName === '' || str_starts_with($customName, '.')) {
                return false;
            }
            $oldExt = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $newExt = strtolower(pathinfo($customName, PATHINFO_EXTENSION));

            if (!PathSecurity::isAllowedForUpload($customName)) {
                if ($oldExt === '' || $oldExt !== $newExt || !PathSecurity::isEditable($customName)) {
                    return false;
                }
            }
        }

        $baseName = $customName !== '' ? $customName : basename($source);

        $destination = $targetDir . '/' . $baseName;
        if (file_exists($destination)) {
            $info = pathinfo($destination);
            $destination = $targetDir . '/' . $info['filename'] . '_copy.' . ($info['extension'] ?? '');
        }

        return @copy($source, $destination);
    }

    /**
     * Read the raw text content of a file (for the code editor).
     */
    public function readFile(string $path): string
    {
        $cleanPath = PathSecurity::sanitize($path);
        if ($cleanPath === '') {
            throw new \RuntimeException("Invalid file path.");
        }

        $target = $this->resolveFullPath($cleanPath);

        if (!is_file($target)) {
            throw new \RuntimeException("File not found: {$cleanPath}");
        }

        $fileName = basename($cleanPath);
        if (!PathSecurity::isEditable($fileName)) {
            throw new \RuntimeException("File is not editable: {$cleanPath}");
        }

        $realTarget = realpath($target);
        $realRoot = realpath($this->resolveFullPath(''));
        if ($realTarget !== false && $realRoot !== false && !str_starts_with(str_replace('\\', '/', $realTarget), str_replace('\\', '/', $realRoot))) {
            throw new \RuntimeException("Access denied: path traversal detected.");
        }

        $content = file_get_contents($target);
        if ($content === false) {
            throw new \RuntimeException("Unable to read file: {$cleanPath}");
        }

        return $content;
    }

    /**
     * Save edited text content back to storage.
     */
    public function saveFile(string $path, string $content): bool
    {
        $cleanPath = PathSecurity::sanitize($path);
        if ($cleanPath === '') {
            return false;
        }

        $fileName = basename($cleanPath);
        if (!PathSecurity::isEditable($fileName)) {
            return false;
        }

        $target = $this->resolveFullPath($cleanPath);

        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $realDir = realpath($dir);
        $realRoot = realpath($this->resolveFullPath(''));
        if ($realDir !== false && $realRoot !== false && !str_starts_with(str_replace('\\', '/', $realDir), str_replace('\\', '/', $realRoot))) {
            return false;
        }

        return file_put_contents($target, $content) !== false;
    }

    /**
     * Handle an uploaded file.
     */
    public function uploadFile(string $subDir, array $file): FileItem
    {
        $cleanSub = PathSecurity::sanitize($subDir);
        $targetDir = $this->resolveFullPath($cleanSub);

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $originalName = $file['name'] ?? 'file';
        $tmpName = $file['tmp_name'] ?? '';

        if (!is_uploaded_file($tmpName) && !is_file($tmpName)) {
            throw new \InvalidArgumentException("Invalid uploaded file.");
        }

        $fileSize = (int) ($file['size'] ?? (is_file($tmpName) ? filesize($tmpName) : 0));
        $maxKb = function_exists('config') ? (int) config('filemanager.max_file_size_kb', 50000) : 50000;
        if ($maxKb > 0 && $fileSize > ($maxKb * 1024)) {
            throw new \InvalidArgumentException("Uploaded file exceeds maximum allowed size.");
        }

        if (!PathSecurity::isAllowedForUpload($originalName)) {
            throw new \InvalidArgumentException("File extension not permitted.");
        }

        // Sanitize filename
        $base = basename(str_replace('\\', '/', $originalName));
        $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $base);

        if ($safeName === '' || !PathSecurity::isAllowedForUpload($safeName)) {
            throw new \InvalidArgumentException("File extension not permitted.");
        }

        $destPath = $targetDir . '/' . $safeName;

        // If file already exists, append unique suffix
        if (file_exists($destPath)) {
            $info = pathinfo($safeName);
            $safeName = $info['filename'] . '_' . time() . '.' . ($info['extension'] ?? '');
            $destPath = $targetDir . '/' . $safeName;
        }

        if (is_uploaded_file($tmpName)) {
            if (!move_uploaded_file($tmpName, $destPath)) {
                throw new \RuntimeException("Failed to move uploaded file.");
            }
        } else {
            if (!copy($tmpName, $destPath)) {
                throw new \RuntimeException("Failed to store file.");
            }
        }

        $relPath = trim($cleanSub . '/' . $safeName, '/');
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $type = PathSecurity::isImage($safeName) ? 'image' : (PathSecurity::isEditable($safeName) ? 'code' : 'file');

        $storageRelative = trim($this->baseDir . '/' . $relPath, '/');
        $url = method_exists($this->getStorageDisk(), 'url')
            ? $this->getStorageDisk()->url($storageRelative)
            : '/' . $storageRelative;

        $prefix = function_exists('config') ? (string) config('filemanager.route_prefix', 'filemanager') : 'filemanager';
        $thumbUrl = $type === 'image' ? "/{$prefix}/thumb?path=" . rawurlencode($relPath) : null;

        return new FileItem(
            name: $safeName,
            path: $relPath,
            isDir: false,
            size: (int) filesize($destPath),
            type: $type,
            url: $url,
            thumbUrl: $thumbUrl,
            extension: $ext,
            updatedAt: date('Y-m-d H:i:s'),
            editorLanguage: PathSecurity::getEditorLanguage($safeName)
        );
    }
}
