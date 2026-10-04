<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "log", "bulksmsbd", "sslwireless", "alphasms"
    | Use "log" during local development and testing to record SMS messages
    | to storage/logs/sms.log without consuming gateway credits.
    |
    */

    'default' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Drivers Configuration
    |--------------------------------------------------------------------------
    */

    'drivers' => [
        'log' => [
            'file' => env('SMS_LOG_FILE', 'storage/logs/sms.log'),
        ],

        'bulksmsbd' => [
            'api_key'   => env('BULKSMSBD_API_KEY', ''),
            'sender_id' => env('BULKSMSBD_SENDER_ID', ''),
            'url'       => 'http://bulksmsbd.net/api/smsapi',
        ],

        'sslwireless' => [
            'api_token' => env('SSLWIRELESS_API_TOKEN', ''),
            'sid'       => env('SSLWIRELESS_SID', ''),
            'url'       => 'https://smsplus.sslwireless.com/api/v3/send-sms',
        ],

        'alphasms' => [
            'api_key'   => env('ALPHASMS_API_KEY', ''),
            'sender_id' => env('ALPHASMS_SENDER_ID', ''),
            'url'       => 'https://api.sms.net.bd/sendsms',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP (One-Time Password) Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for automated OTP code generation, expiry, and rate limiting.
    |
    */

    'otp' => [
        'length'           => (int) env('SMS_OTP_LENGTH', 6),
        'expires_in'       => (int) env('SMS_OTP_EXPIRES_IN', 300), // in seconds (5 min)
        'max_attempts'     => (int) env('SMS_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown'  => (int) env('SMS_OTP_RESEND_COOLDOWN', 60), // in seconds (1 min)
    ],
];
