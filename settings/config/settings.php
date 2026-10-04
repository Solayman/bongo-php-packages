<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database Table
    |--------------------------------------------------------------------------
    |
    | The database table name where application settings are persisted.
    |
    */
    'table' => env('SETTINGS_TABLE', 'settings'),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | When enabled, settings are cached in memory for the request duration
    | and optionally in persistent cache to avoid unnecessary database queries.
    |
    */
    'cache_enabled' => (bool) env('SETTINGS_CACHE_ENABLED', true),
    'cache_ttl' => (int) env('SETTINGS_CACHE_TTL', 3600),
    'cache_key' => 'bongo_app_settings',

    /*
    |--------------------------------------------------------------------------
    | Default Group
    |--------------------------------------------------------------------------
    |
    | The default category or group assigned to new settings if unspecified.
    |
    */
    'default_group' => 'general',
];
