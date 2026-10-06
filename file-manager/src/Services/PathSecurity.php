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
        if (str_contains($filename, "\0")) {
            return false;
        }

        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || str_starts_with($filename, '.')) {
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $valid = function_exists('config')
            ? (array) config('filemanager.valid_code_extensions', ['js', 'json', 'css', 'sql', 'md', 'txt', 'xml', 'yml'])
            : ['js', 'json', 'css', 'sql', 'md', 'txt', 'xml', 'yml'];

        // Never editable, even if listed in the config: saving a .php file under public/ would run it
        return in_array($ext, $valid, true) && self::isAllowedForUpload($filename);
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
        if (str_contains($filename, "\0")) {
            return false;
        }

        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || str_starts_with($filename, '.')) {
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }

        // Dangerous executable extensions and server scripts always blocked
        $blocked = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phps', 'phtml', 'phar', 'pgif', 'inc',
            'htaccess', 'htpasswd', 'ini', 'user.ini', 'env', 'conf', 'config',
            'exe', 'dll', 'so', 'dylib', 'bin', 'app', 'com', 'scr', 'msi', 'msp', 'hta', 'cpl', 'gadget',
            'asp', 'aspx', 'axd', 'asmx', 'ashx', 'asx', 'cfm', 'cfc', 'jsp', 'jspx', 'jsw', 'jsv', 'jspf',
            'cgi', 'pl', 'py', 'pyc', 'pyo', 'rb',
            'sh', 'bash', 'zsh', 'csh', 'ksh', 'cmd', 'bat', 'ps1', 'ps2', 'ps1xml', 'ps2xml', 'psc1', 'psc2',
            'vbs', 'vbe', 'ws', 'wsf', 'wsc', 'wsh', 'reg'
        ];
        if (in_array($ext, $blocked, true)) {
            return false;
        }

        // HTML and SVG run scripts on this site's origin when opened (stored XSS); opt in only on purpose
        $activeContent = ['html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'mht', 'mhtml'];
        $allowActive = function_exists('config') && (bool) config('filemanager.allow_html_svg', false);
        if (!$allowActive && in_array($ext, $activeContent, true)) {
            return false;
        }

        // Multi-extension execution bypass protection (e.g. avatar.php.jpg, test.php7.png)
        if (preg_match('/\.(php[0-9]?|phtml|phar|pht|phps|pgif|asp|aspx|jsp|jspx|cgi|pl|py|sh|bash|cmd|bat|exe|htaccess)\./i', $filename)) {
            return false;
        }

        return true;
    }
}

