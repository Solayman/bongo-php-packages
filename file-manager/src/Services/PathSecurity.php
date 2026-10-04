<?php

declare(strict_types=1);

namespace Bongo\Packages\FileManager\Services;

class PathSecurity
{
    /**
     * Sanitize a relative file/folder path and ensure it cannot traverse outside the root.
     */
    public static function sanitize(string $path): string
    {
        // Truncate at null byte if present
        if (str_contains($path, "\0")) {
            $path = substr($path, 0, (int) strpos($path, "\0"));
        }

        // Normalize separators
        $path = str_replace('\\', '/', $path);

        // Explode and filter out '.' and '..'
        $parts = explode('/', $path);
        $safeParts = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($safeParts);
                continue;
            }
            $safeParts[] = $part;
        }

        return implode('/', $safeParts);
    }

    /**
     * Check if a filename extension is an image.
     */
    public static function isImage(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $valid = function_exists('config')
            ? (array) config('filemanager.valid_image_extensions', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])
            : ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

        return in_array($ext, $valid, true);
    }

    /**
     * Check if a filename extension can be edited in the code editor.
     */
    public static function isEditable(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $valid = function_exists('config')
            ? (array) config('filemanager.valid_code_extensions', ['php', 'js', 'json', 'css', 'html', 'sql', 'md', 'txt', 'xml', 'yml', 'env'])
            : ['php', 'js', 'json', 'css', 'html', 'sql', 'md', 'txt', 'xml', 'yml', 'env'];

        return in_array($ext, $valid, true);
    }

    /**
     * Detect editor language from extension.
     */
    public static function getEditorLanguage(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'php' => 'php',
            'js', 'jsx', 'ts', 'tsx' => 'javascript',
            'json' => 'json',
            'css', 'scss', 'less' => 'css',
            'html', 'htm' => 'html',
            'sql' => 'sql',
            'md', 'markdown' => 'markdown',
            'xml', 'svg' => 'xml',
            'yml', 'yaml' => 'yaml',
            'sh', 'bash' => 'shell',
            'bat', 'cmd' => 'bat',
            default => 'plaintext',
        };
    }

    /**
     * Check if a filename is allowed for upload based on extension.
     */
    public static function isAllowedForUpload(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }

        // Dangerous executable extensions always blocked
        $blocked = ['exe', 'dll', 'so', 'phtml', 'phar'];
        if (in_array($ext, $blocked, true)) {
            return false;
        }

        return true;
    }
}
