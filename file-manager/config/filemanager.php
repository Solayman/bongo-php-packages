<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The storage disk to use for managing files (e.g. 'public', 'local', 's3', 'ftp').
    | Uses the default disk from config/storage.php if omitted.
    |
    */
    'disk' => env('FILEMANAGER_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Base Directory
    |--------------------------------------------------------------------------
    |
    | The root directory inside the chosen storage disk for the file manager.
    |
    */
    'base_directory' => env('FILEMANAGER_BASE_DIR', 'uploads'),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix & Middleware
    |--------------------------------------------------------------------------
    |
    | The URL prefix and middleware stack applied to file manager routes.
    |
    */
    'route_prefix' => env('FILEMANAGER_PREFIX', 'filemanager'),
    // Signed-in users only. Add your own gate too, e.g. ['web', 'auth', 'can:manage-files']
    'middleware' => ['web', 'auth'],

    // HTML and SVG files can run scripts on your site when opened. Leave false unless only trusted admins upload.
    'allow_html_svg' => (bool) env('FILEMANAGER_ALLOW_HTML_SVG', false),

    /*
    |--------------------------------------------------------------------------
    | Multi-User Folder Isolation
    |--------------------------------------------------------------------------
    |
    | If enabled, each authenticated user accesses their own isolated subfolder
    | ('users/{user_id}'), with an optional shared folder ('shares').
    |
    */
    'user_folder_enabled' => (bool) env('FILEMANAGER_USER_FOLDERS', false),

    /*
    |--------------------------------------------------------------------------
    | Upload Restrictions
    |--------------------------------------------------------------------------
    |
    | Maximum allowed file size in kilobytes and allowed extension lists.
    |
    */
    'max_file_size_kb' => (int) env('FILEMANAGER_MAX_SIZE_KB', 50000), // 50 MB

    'valid_image_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif' // svg only with allow_html_svg
    ],

    'valid_code_extensions' => [
        'js', 'ts', 'json', 'css', 'scss', // never php (it would run); html only with allow_html_svg
        'sql', 'md', 'txt', 'xml', 'yml', 'yaml', 'log', 'csv'
    ],

    'valid_document_extensions' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'tar', 'gz'
    ],

    /*
    |--------------------------------------------------------------------------
    | Professional Code Editor Settings
    |--------------------------------------------------------------------------
    |
    | Default theme and configuration for the built-in code editor.
    |
    */
    'editor' => [
        'theme' => 'vs-dark', // 'vs-dark' or 'vs' (light)
        'tab_size' => 4,
        'word_wrap' => true,
        'line_numbers' => true,
    ],
];
