<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Captcha Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "turnstile", "recaptcha", "hcaptcha", "math", "image"
    |
    | "turnstile": Cloudflare Turnstile (recommended modern anti-bot)
    | "recaptcha": Google reCAPTCHA v2 (checkbox) or v3 (invisible score)
    | "hcaptcha":  hCaptcha verification
    | "math":      Zero-dependency session math challenge (works offline/local)
    | "image":     Pure-PHP GD distorted challenge image (zero external API keys)
    |
    */
    'driver' => env('CAPTCHA_DRIVER', 'turnstile'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile Settings
    |--------------------------------------------------------------------------
    */
    'turnstile' => [
        'site_key'   => env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
        'theme'      => env('TURNSTILE_THEME', 'auto'), // auto, light, dark
        'size'       => env('TURNSTILE_SIZE', 'normal'), // normal, compact, flexible
    ],

    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA Settings
    |--------------------------------------------------------------------------
    */
    'recaptcha' => [
        'site_key'        => env('RECAPTCHA_SITE_KEY', ''),
        'secret_key'      => env('RECAPTCHA_SECRET_KEY', ''),
        'version'         => env('RECAPTCHA_VERSION', 'v2'), // 'v2' or 'v3'
        'score_threshold' => (float) env('RECAPTCHA_SCORE_THRESHOLD', 0.5),
        'theme'           => env('RECAPTCHA_THEME', 'light'), // light, dark
        'size'            => env('RECAPTCHA_SIZE', 'normal'), // normal, compact
    ],

    /*
    |--------------------------------------------------------------------------
    | hCaptcha Settings
    |--------------------------------------------------------------------------
    */
    'hcaptcha' => [
        'site_key'   => env('HCAPTCHA_SITE_KEY', ''),
        'secret_key' => env('HCAPTCHA_SECRET_KEY', ''),
        'theme'      => env('HCAPTCHA_THEME', 'light'),
        'size'       => env('HCAPTCHA_SIZE', 'normal'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Math Captcha Settings (Pure PHP / Offline)
    |--------------------------------------------------------------------------
    */
    'math' => [
        'session_key' => 'bongo_captcha_math',
        'min'         => 1,
        'max'         => 20,
        'operators'   => ['+', '-', '*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Captcha Settings (Pure PHP GD / Offline)
    |--------------------------------------------------------------------------
    */
    'image' => [
        'session_key' => 'bongo_captcha_image',
        'width'       => 150,
        'height'      => 45,
        'length'      => 5,
        'characters'  => '23456789abcdefghkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ',
        'noise_lines' => 4,
        'noise_dots'  => 50,
    ],
];
