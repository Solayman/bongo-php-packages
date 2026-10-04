<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Two-Factor Issuer & Settings
    |--------------------------------------------------------------------------
    |
    | The issuer name displayed inside Authenticator apps (Google Authenticator,
    | Authy, 1Password, Microsoft Authenticator).
    |
    */
    'issuer' => env('TWO_FACTOR_ISSUER', env('APP_NAME', 'Bongo Framework')),

    /*
    |--------------------------------------------------------------------------
    | TOTP Code Window & Digits
    |--------------------------------------------------------------------------
    |
    | Number of digits in the TOTP code (default 6) and time step in seconds (30).
    | Window allows checking clock drift (+/- 1 time step = 30 seconds).
    |
    */
    'digits' => 6,
    'period' => 30,
    'window' => 1,

    /*
    |--------------------------------------------------------------------------
    | Recovery / Backup Codes
    |--------------------------------------------------------------------------
    |
    | How many single-use recovery backup codes to generate when enabling 2FA.
    |
    */
    'recovery_codes_count' => 8,

    /*
    |--------------------------------------------------------------------------
    | Redirect Routes
    |--------------------------------------------------------------------------
    |
    | Destination after successful verification or when 2FA challenge is needed.
    |
    */
    'redirect_after_verification' => '/',
    'challenge_route' => '/2fa/challenge',
];
