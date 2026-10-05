<?php

declare(strict_types=1);

namespace Bongo\Packages\FileManager\Controllers;

use Bongo\Packages\FileManager\FileManager;
use Bongo\Packages\FileManager\Services\PathSecurity;
use System\Http\Request;

class FileManagerController
{
    /**
     * Render the main file manager UI.
     */
    public function index(Request $request): string
    {
        if (!headers_sent()) {
            header('Permissions-Policy: unload=*');
        }

        $viewPath = __DIR__ . '/../../views/index.php';
        if (file_exists($viewPath)) {
            extract([
                'type' => $request->query('type', 'all'),
                'callback' => $request->query('callback'),
                'field_name' => $request->query('field_name'),
                'prefix' => function_exists('config') ? config('filemanager.route_prefix', 'filemanager') : 'filemanager',
            ]);
            ob_start();
            require $viewPath;
            return (string) ob_get_clean();
        }

        return "File Manager view not found.";
    }

    /**
     * Get files and folders list in JSON format.
     */
    public function items(Request $request): array
    {
        $path = (string) $request->query('path', '');
        $data = FileManager::getItems($path);

        $typeFilter = (string) $request->query('type', 'all');
        if ($typeFilter === 'image' || $typeFilter === 'images') {
            $data['files'] = array_values(array_filter($data['files'], fn($f) => $f->type === 'image'));
        } elseif ($typeFilter === 'code') {
            $data['files'] = array_values(array_filter($data['files'], fn($f) => $f->type === 'code'));
        }

        return [
            'status' => 'success',
            'path' => $path,
            'folders' => $data['folders'],
            'files' => $data['files'],
        ];
    }

    /**
     * Get folder tree structure in JSON.
     */
    public function tree(Request $request): array
    {
        return [
            'status' => 'success',
            'tree' => FileManager::getTree(''),
        ];
    }

    /**
     * Upload one or multiple files.
     */
    public function upload(Request $request): array
    {
        $subDir = (string) ($request->input('path') ?? '');
        $uploaded = [];
        $errors = [];

        $files = $_FILES['files'] ?? ($_FILES['file'] ?? null);

        if ($files === null) {
            return ['status' => 'error', 'message' => 'No files uploaded.'];
        }

        // Handle single or multiple file uploads
        if (is_array($files['name'])) {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $file = [
                    'name' => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i],
                    'error' => $files['error'][$i],
                ];

                try {
                    $item = FileManager::service()->uploadFile($subDir, $file);
                    $uploaded[] = $item;
                } catch (\Throwable $e) {
                    $errors[] = $file['name'] . ': ' . $e->getMessage();
                }
            }
        } else {
            if ($files['error'] === UPLOAD_ERR_OK) {
                try {
                    $item = FileManager::service()->uploadFile($subDir, $files);
                    $uploaded[] = $item;
                } catch (\Throwable $e) {
                    $errors[] = $files['name'] . ': ' . $e->getMessage();
                }
            }
        }

        return [
            'status' => empty($errors) ? 'success' : (empty($uploaded) ? 'error' : 'partial'),
            'uploaded' => $uploaded,
            'errors' => $errors,
        ];
    }

    /**
     * Create a new folder.
     */
    public function folder(Request $request): array
    {
        $parent = (string) ($request->input('path') ?? '');
        $name = (string) ($request->input('name') ?? '');

        if ($name === '') {
            return ['status' => 'error', 'message' => 'Folder name is required.'];
        }

        $ok = FileManager::createFolder($parent, $name);
        return [
            'status' => $ok ? 'success' : 'error',
            'message' => $ok ? 'Folder created.' : 'Failed to create folder or folder already exists.',
        ];
    }

    /**
     * Delete a file or directory.
     */
    public function delete(Request $request): array
    {
        $path = (string) ($request->input('path') ?? '');
        $ok = FileManager::deleteItem($path);

        return [
            'status' => $ok ? 'success' : 'error',
            'message' => $ok ? 'Item deleted.' : 'Failed to delete item.',
        ];
    }

    /**
     * Rename a file or directory.
     */
    public function rename(Request $request): array
    {
        $path = (string) ($request->input('path') ?? '');
        $newName = (string) ($request->input('new_name') ?? '');

        if ($newName === '') {
            return ['status' => 'error', 'message' => 'New name is required.'];
        }

        $ok = FileManager::renameItem($path, $newName);
        return [
            'status' => $ok ? 'success' : 'error',
            'message' => $ok ? 'Item renamed.' : 'Failed to rename item.',
        ];
    }

    /**
     * Move a file or directory.
     */
    public function move(Request $request): array
    {
        $path = (string) ($request->input('path') ?? '');
        $target = (string) ($request->input('target') ?? '');

        $ok = FileManager::moveItem($path, $target);
        return [
            'status' => $ok ? 'success' : 'error',
            'message' => $ok ? 'Item moved.' : 'Failed to move item.',
        ];
    }

    /**
     * Copy a file.
     */
    public function copy(Request $request): array
    {
        $path = (string) ($request->input('path') ?? '');
        $target = (string) ($request->input('target') ?? '');
        $newName = $request->input('new_name') !== null ? (string) $request->input('new_name') : null;

        $ok = FileManager::service()->copyItem($path, $target, $newName);
        return [
            'status' => $ok ? 'success' : 'error',
            'message' => $ok ? 'Item copied.' : 'Failed to copy item.',
        ];
    }

    /**
     * Read file content for code editor.
     */
    public function read(Request $request): array
    {
        $path = (string) ($request->query('path') ?? '');

        try {
            $content = FileManager::readFile($path);
            $lang = PathSecurity::getEditorLanguage($path);

            return [
                'status' => 'success',
                'path' => $path,
                'name' => basename($path),
                'language' => $lang,
                'content' => $content,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Save edited file content from code editor.
     */
    public function save(Request $request): array
    {
        $path = (string) ($request->input('path') ?? '');
        $content = (string) ($request->input('content') ?? '');

        if ($path === '') {
            return ['status' => 'error', 'message' => 'Path is required.'];
        }

        $ok = FileManager::saveFile($path, $content);

        return [
            'status' => $ok ? 'success' : 'error',
            'message' => $ok ? 'File saved successfully.' : 'Failed to save file.',
        ];
    }

    /**
     * Download a file from storage.
     */
    public function download(Request $request): void
    {
        $path = (string) ($request->query('path') ?? '');
        $cleanPath = PathSecurity::sanitize($path);
        $fullPath = FileManager::service()->resolveFullPath($cleanPath);

        if (!is_file($fullPath)) {
            if (!headers_sent()) {
                http_response_code(404);
            }
            echo "File not found.";
            return;
        }

        $filename = basename($fullPath);
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        $size = (int) filesize($fullPath);

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . $size);
        }

        readfile($fullPath);
        if (!class_exists('System\\Testing\\TestCase', false)) {
            exit;
        }
    }

    /**
     * Generate and serve a high-quality cached thumbnail.
     */
    public function thumbnail(Request $request): void
    {
        $path = (string) ($request->query('path') ?? '');
        $cleanPath = PathSecurity::sanitize($path);
        $fullPath = FileManager::service()->resolveFullPath($cleanPath);

        if (!is_file($fullPath) || !PathSecurity::isImage($fullPath)) {
            if (!headers_sent()) {
                http_response_code(404);
            }
            echo "Image not found.";
            return;
        }

        $width = (int) ($request->query('w', 200));
        $height = (int) ($request->query('h', 200));
        if ($width <= 0 || $width > 800) $width = 200;
        if ($height <= 0 || $height > 800) $height = 200;

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if ($ext === 'svg' || !extension_loaded('gd')) {
            $this->streamFile($fullPath);
            return;
        }

        $mtime = filemtime($fullPath) ?: 0;
        $hash = md5($cleanPath . '_' . $mtime . "_{$width}x{$height}");
        $cacheDir = function_exists('storage_path')
            ? storage_path('cache/filemanager_thumbs')
            : sys_get_temp_dir() . '/filemanager_thumbs';

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . '/' . $hash . '.webp';
        if (file_exists($cacheFile)) {
            $this->streamFile($cacheFile, 'image/webp');
            return;
        }

        $imgInfo = @getimagesize($fullPath);
        if (!$imgInfo) {
            $this->streamFile($fullPath);
            return;
        }

        $srcW = $imgInfo[0];
        $srcH = $imgInfo[1];
        $mime = $imgInfo['mime'];

        $srcImg = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($fullPath),
            'image/png'  => @imagecreatefrompng($fullPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : null,
            'image/gif'  => @imagecreatefromgif($fullPath),
            default      => null,
        };

        if (!$srcImg) {
            $this->streamFile($fullPath);
            return;
        }

        $ratio = min($width / $srcW, $height / $srcH);
        $dstW = (int) max(1, round($srcW * $ratio));
        $dstH = (int) max(1, round($srcH * $ratio));

        $dstImg = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
        imagefilledrectangle($dstImg, 0, 0, $dstW, $dstH, $transparent);

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        if (function_exists('imagewebp')) {
            imagewebp($dstImg, $cacheFile, 88);
            $outMime = 'image/webp';
        } else {
            imagejpeg($dstImg, $cacheFile, 85);
            $outMime = 'image/jpeg';
        }

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        $this->streamFile($cacheFile, $outMime);
    }

    protected function streamFile(string $filePath, ?string $mime = null): void
    {
        if (!is_file($filePath)) {
            if (!headers_sent()) {
                http_response_code(404);
            }
            return;
        }

        $detectedMime = $mime ?? (mime_content_type($filePath) ?: 'application/octet-stream');
        $size = (int) filesize($filePath);
        $mtime = filemtime($filePath) ?: time();
        $etag = '"' . md5($filePath . $mtime . $size) . '"';

        if (!headers_sent()) {
            header('Content-Type: ' . $detectedMime);
            header('Content-Length: ' . $size);
            header('Cache-Control: public, max-age=86400');
            header('ETag: ' . $etag);

            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
                http_response_code(304);
                return;
            }
        }

        readfile($filePath);
        if (!class_exists('System\\Testing\\TestCase', false)) {
            exit;
        }
    }

    /**
     * Return javascript helper for standalone and TinyMCE integrations.
     */
    public function script(): string
    {
        $path = __DIR__ . '/../../views/script.php';
        if (file_exists($path)) {
            $content = (string) file_get_contents($path);
            $content = preg_replace('/^<script>\s*/i', '', $content);
            $content = preg_replace('/<\/script>\s*$/i', '', (string) $content);
            if (!headers_sent()) {
                header('Content-Type: application/javascript; charset=utf-8');
            }
            return (string) $content;
        }

        return '';
    }
}
