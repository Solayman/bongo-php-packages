<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Backup Storage Disk
    |--------------------------------------------------------------------------
    |
    | The storage disk where backup archives are persisted. Can be 'local'
    | (saving to storage/backups) or 's3' (saving to AWS S3, Cloudflare R2,
    | DigitalOcean Spaces, or MinIO).
    |
    */
    'disk' => env('BACKUP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Custom Local Backup Destination Directory
    |--------------------------------------------------------------------------
    |
    | When storing locally, defaults to storage/backups within project root.
    |
    */
    'destination_path' => env('BACKUP_LOCAL_PATH', 'storage/backups'),

    /*
    |--------------------------------------------------------------------------
    | Database Backup Configuration
    |--------------------------------------------------------------------------
    |
    | The connection to dump. If null, the application default DB connection
    | is used. Supports SQLite, MySQL, and PostgreSQL with zero dependencies.
    |
    */
    'database' => [
        'connection' => env('BACKUP_DB_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Files & Assets Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Directories to include in the file archive. Excluded directories like
    | .git, node_modules, cache, sessions, and vendor are skipped.
    |
    */
    'files' => [
        'directories' => [
            'storage/app',
            'public/storage',
        ],
        'exclude' => [
            '.git',
            'node_modules',
            'vendor',
            'cache',
            'sessions',
            '.env',
            'backups',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention & Cleanup Policy
    |--------------------------------------------------------------------------
    |
    | Automatically clean older backups after each run.
    |
    */
    'cleanup' => [
        'auto' => (bool) env('BACKUP_CLEANUP_AUTO', true),
        'keep_count' => (int) env('BACKUP_KEEP_COUNT', 10),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
    ],
];
