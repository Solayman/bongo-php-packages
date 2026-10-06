<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhooks Configuration
    |--------------------------------------------------------------------------
    */
    'default_secret' => env('WEBHOOK_SECRET', ''),

    'timeout' => (int) env('WEBHOOK_TIMEOUT', 10),

    'verify_ssl' => (bool) env('WEBHOOK_VERIFY_SSL', true),

    'tolerance_seconds' => (int) env('WEBHOOK_TOLERANCE', 300),

    // Refuse URLs that resolve to private, loopback, link-local or reserved addresses (SSRF guard
    // for user-entered webhook URLs). On by default in production; turn off to test against localhost.
    'block_private_networks' => (bool) env('WEBHOOK_BLOCK_PRIVATE_NETWORKS', env('APP_ENV', 'production') === 'production'),
];
