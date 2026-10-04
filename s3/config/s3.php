<?php

return [
    /*
    |--------------------------------------------------------------------------
    | S3 / Object Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Compatible with Amazon S3, DigitalOcean Spaces, Cloudflare R2, MinIO,
    | Wasabi, and Backblaze B2. Uses pure PHP AWS Signature Version 4.
    |
    */

    'driver' => 's3',

    // Access Credentials
    'key'    => env('AWS_ACCESS_KEY_ID', ''),
    'secret' => env('AWS_SECRET_ACCESS_KEY', ''),

    // AWS Region or provider datacenter (e.g. 'us-east-1', 'nyc3', 'auto')
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    // Target bucket name
    'bucket' => env('AWS_BUCKET', ''),

    // Public URL prefix / CDN domain (e.g. 'https://cdn.example.com' or Spaces CDN)
    'url' => env('AWS_URL'),

    // Custom S3-compatible API endpoint (Required for MinIO, DigitalOcean, Cloudflare R2)
    // Examples:
    //   MinIO:          'http://127.0.0.1:9000'
    //   DigitalOcean:   'https://nyc3.digitaloceanspaces.com'
    //   Cloudflare R2:  'https://<account-id>.r2.cloudflarestorage.com'
    'endpoint' => env('AWS_ENDPOINT'),

    // Set true for MinIO or local test servers that require path-style URLs: http://host/bucket/file
    'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', false),

    // Throw exceptions on network / permission failure (default: true)
    'throw' => (bool) env('AWS_THROW_EXCEPTIONS', true),
];
