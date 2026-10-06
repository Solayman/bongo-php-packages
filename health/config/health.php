<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Health Check Route & Access
    |--------------------------------------------------------------------------
    |
    | Secret token required to view detailed system telemetry (memory, paths,
    | database credentials). When omitted or null, public status is returned.
    |
    */
    'secret_token' => env('HEALTH_SECRET_TOKEN', null),

    // Reuse the last report for this many seconds (0 = run all checks on every request)
    'cache_seconds' => (int) env('HEALTH_CACHE_SECONDS', 10),

    /*
    |--------------------------------------------------------------------------
    | Disk Space Threshold
    |--------------------------------------------------------------------------
    |
    | Minimum free disk space in megabytes before flagging a warning/failure.
    |
    */
    'disk_min_free_mb' => (int) env('HEALTH_DISK_MIN_FREE_MB', 500),

    /*
    |--------------------------------------------------------------------------
    | Database Latency Threshold (Milliseconds)
    |--------------------------------------------------------------------------
    |
    | Threshold in milliseconds above which database responsiveness is flagged.
    |
    */
    'database_max_latency_ms' => 200.0,
];
