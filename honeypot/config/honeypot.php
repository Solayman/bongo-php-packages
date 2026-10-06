<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Honeypot Configuration
    |--------------------------------------------------------------------------
    */
    // Decoy field name (bots will auto-fill this tempting field)
    'decoy_field' => env('HONEYPOT_DECOY_FIELD', 'my_name'),

    // Signed timestamp field name
    'time_field' => env('HONEYPOT_TIME_FIELD', '_hp_time'),

    // Minimum seconds required between rendering and submission (humans take > 2 sec)
    'min_seconds' => (int) env('HONEYPOT_MIN_SECONDS', 2),

    // Maximum validity period of form submission (24 hours by default)
    'max_seconds' => (int) env('HONEYPOT_MAX_SECONDS', 86400),

    // Enable/disable honeypot protection globally
    'enabled' => (bool) env('HONEYPOT_ENABLED', true),
];
