<?php

return [
    /*
    |--------------------------------------------------------------------------
    | bKash Merchant API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials for bKash Tokenized Checkout (PGW v1.2.0-beta).
    | Obtain sandbox credentials from the bKash Developer Portal:
    | https://developer.bka.sh
    |
    */

    'app_key'      => env('BKASH_APP_KEY', ''),
    'app_secret'   => env('BKASH_APP_SECRET', ''),
    'username'     => env('BKASH_USERNAME', ''),
    'password'     => env('BKASH_PASSWORD', ''),
    'sandbox'      => (bool) env('BKASH_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Currency & Intent
    |--------------------------------------------------------------------------
    */
    'currency'     => 'BDT',
    'intent'       => 'sale',

    /*
    |--------------------------------------------------------------------------
    | Callback Route
    |--------------------------------------------------------------------------
    |
    | Default redirect target after customer completes or cancels payment on
    | the bKash checkout page.
    |
    */
    'callback_url' => env('BKASH_CALLBACK_URL', '/bkash/callback'),
];
