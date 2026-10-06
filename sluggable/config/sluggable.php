<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Slug Separator
    |--------------------------------------------------------------------------
    |
    | Character used to separate words in the generated slug (usually '-' or '_').
    |
    */
    'separator' => env('SLUGGABLE_SEPARATOR', '-'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Slug Length
    |--------------------------------------------------------------------------
    |
    | Maximum allowed character length for generated slugs (0 = unlimited).
    |
    */
    'max_length' => 120,

    /*
    |--------------------------------------------------------------------------
    | Update Slug on Model Updates
    |--------------------------------------------------------------------------
    |
    | When true, the slug will regenerate whenever the source attribute changes.
    | When false (recommended for SEO), the slug remains stable once created.
    |
    */
    'on_update' => false,

    /*
    |--------------------------------------------------------------------------
    | Reserved Slugs
    |--------------------------------------------------------------------------
    |
    | Slugs that should never be generated to prevent routing conflicts.
    |
    */
    'reserved' => [
        'admin',
        'login',
        'logout',
        'register',
        'api',
        'dashboard',
        'settings',
        'cart',
        'checkout',
        'search',
        'profile',
    ],
];
