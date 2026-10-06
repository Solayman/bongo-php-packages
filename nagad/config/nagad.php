<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nagad Merchant Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials for Nagad Payment Gateway (PGW v-0.2).
    | Keys can be passed as raw PEM strings, base64-encoded strings, or paths
    | to .pem files stored on disk (e.g. storage/nagad/private.pem).
    |
    */

    'merchant_id'          => env('NAGAD_MERCHANT_ID', ''),
    'merchant_private_key' => env('NAGAD_MERCHANT_PRIVATE_KEY', ''),
    'nagad_public_key'     => env('NAGAD_PUBLIC_KEY', ''),
    'sandbox'              => (bool) env('NAGAD_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Currency & API Version
    |--------------------------------------------------------------------------
    */
    'currency'             => 'BDT',
    'api_version'          => 'v-0.2',

    /*
    |--------------------------------------------------------------------------
    | Callback Route
    |--------------------------------------------------------------------------
    |
    | Application route where Nagad redirects after payment completion or cancel.
    |
    */
    'callback_url'         => env('NAGAD_CALLBACK_URL', '/nagad/callback'),

    // POST /nagad/pay takes order_id and amount from the browser: demo/testing only
    'demo_checkout'        => (bool) env('NAGAD_DEMO_CHECKOUT', false),
];
