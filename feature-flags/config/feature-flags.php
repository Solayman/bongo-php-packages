<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: 'database', 'array'
    |
    */
    'driver' => env('FEATURE_FLAGS_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | Database table name when using the 'database' driver.
    |
    */
    'table' => 'features',

    /*
    |--------------------------------------------------------------------------
    | Static Features Configuration
    |--------------------------------------------------------------------------
    |
    | Default feature flag states that can be toggled without database lookup.
    |
    */
    'flags' => [
        // 'new-checkout' => false,
        // 'dark-mode' => true,
    ],
];
