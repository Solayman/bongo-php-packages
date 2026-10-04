<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default SEO Metadata
    |--------------------------------------------------------------------------
    |
    | Default values used when page-specific SEO attributes are omitted.
    |
    */
    'defaults' => [
        'title' => env('APP_NAME', 'Bongo Application'),
        'title_separator' => ' - ',
        'description' => 'A high-performance modern web application built on Bongo.',
        'keywords' => [],
        'canonical' => null,
        'robots' => 'index, follow',

        'og' => [
            'type' => 'website',
            'site_name' => env('APP_NAME', 'Bongo Application'),
            'image' => null,
        ],

        'twitter' => [
            'card' => 'summary_large_image',
            'site' => null,
            'creator' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap Configuration
    |--------------------------------------------------------------------------
    |
    | Destination path and route options for XML sitemaps.
    |
    */
    'sitemap' => [
        'output_path' => 'public/sitemap.xml',
    ],
];
