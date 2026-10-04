<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | FTP Server Host & Port
    |--------------------------------------------------------------------------
    */
    'host' => env('FTP_HOST', '127.0.0.1'),
    'port' => (int) env('FTP_PORT', 21),

    /*
    |--------------------------------------------------------------------------
    | Authentication Credentials
    |--------------------------------------------------------------------------
    */
    'username' => env('FTP_USERNAME', 'anonymous'),
    'password' => env('FTP_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Storage Directory Root & Options
    |--------------------------------------------------------------------------
    */
    'root' => env('FTP_ROOT', '/'),
    'passive' => (bool) env('FTP_PASSIVE', true),
    'ssl' => (bool) env('FTP_SSL', false),
    'timeout' => (int) env('FTP_TIMEOUT', 30),
];
