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
];
