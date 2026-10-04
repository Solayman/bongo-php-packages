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

        $ok = FileManager::service()->copyItem($path, $target);
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
        exit;
    }
}
