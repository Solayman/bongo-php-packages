<?php

declare(strict_types=1);

if (!function_exists('filemanager_url')) {
    /**
     * Generate URL to the file manager popup or standalone window.
     */
    function filemanager_url(string $type = 'all', ?string $callback = null): string
    {
        $prefix = function_exists('config') ? (string) config('filemanager.route_prefix', 'filemanager') : 'filemanager';
        $params = ['type' => $type];

        if ($callback !== null) {
            $params['callback'] = $callback;
        }

        return '/' . $prefix . '?' . http_build_query($params);
    }
}
