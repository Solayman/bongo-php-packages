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
        // Server scripts and executable extensions can never be edited or saved into storage
        $blocked = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'phar', 'shtml',
            'exe', 'dll', 'so', 'cgi', 'pl', 'asp', 'aspx', 'jsp',
            'sh', 'bash', 'cmd', 'bat', 'com', 'vbs', 'scr', 'msi', 'hta', 'py', 'env', 'htaccess'
        ];
        if (in_array($ext, $blocked, true)) {
            return false;
        }

        $valid = function_exists('config')
            ? (array) config('filemanager.valid_code_extensions', ['js', 'ts', 'json', 'css', 'scss', 'html', 'htm', 'sql', 'md', 'txt', 'xml', 'yml', 'yaml', 'csv', 'log'])
            : ['js', 'ts', 'json', 'css', 'scss', 'html', 'htm', 'sql', 'md', 'txt', 'xml', 'yml', 'yaml', 'csv', 'log'];

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
        $filename = basename($filename);
        if ($filename === '' || str_starts_with($filename, '.')) {
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }

        // Dangerous executable extensions and server scripts always blocked
        $blocked = [
            'exe', 'dll', 'so', 'phtml', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'jsp',
            'sh', 'bash', 'cmd', 'bat', 'com', 'vbs', 'scr', 'msi', 'hta', 'py'
        ];
        if (in_array($ext, $blocked, true)) {
            return false;
        }

        // Multi-extension execution bypass protection (e.g. avatar.php.jpg)
        if (preg_match('/\.(php[0-9]?|phtml|phar|pht|shtml|cgi|pl|asp|aspx|jsp|sh|py)(\.|$)/i', $filename)) {
            return false;
        }

        return true;
    }
}
