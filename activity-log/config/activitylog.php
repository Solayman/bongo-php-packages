<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Log Table
    |--------------------------------------------------------------------------
    |
    | The database table name where activity records are stored.
    |
    */
    'table' => env('ACTIVITY_LOG_TABLE', 'activity_log'),

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel / Name
    |--------------------------------------------------------------------------
    |
    | The default log name when none is explicitly specified.
    |
    */
    'default_log_name' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Retention Policy (Days)
    |--------------------------------------------------------------------------
    |
    | Number of days before old activity logs can be pruned via `activity:clean`.
    |
    */
    'delete_records_older_than_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 60),

    /*
    |--------------------------------------------------------------------------
    | Request Telemetry
    |--------------------------------------------------------------------------
    |
    | Whether to record client IP address and User-Agent header with each log.
    |
    */
    'capture_ip' => true,
    'capture_user_agent' => true,
];
