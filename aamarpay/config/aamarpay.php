<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AamarPay Merchant Credentials
    |--------------------------------------------------------------------------
    |
    | Get your Store ID and Signature Key from your AamarPay merchant portal.
    | Set AAMARPAY_SANDBOX=true for sandbox testing (default store: aamarpaytest).
    |
    */
    'store_id'      => env('AAMARPAY_STORE_ID', 'aamarpaytest'),
    'signature_key' => env('AAMARPAY_SIGNATURE_KEY', '28c78bb1f45112f5a40b956f9d102abb'),
    'sandbox'       => (bool) env('AAMARPAY_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Default Transaction Currency
    |--------------------------------------------------------------------------
    |
    | Supported currencies: BDT, USD
    |
    */
    'currency' => env('AAMARPAY_CURRENCY', 'BDT'),

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    |
    | URLs where AamarPay will redirect upon payment completion or failure.
    |
    */
    'success_url' => env('AAMARPAY_SUCCESS_URL', '/aamarpay/success'),
    'fail_url'    => env('AAMARPAY_FAIL_URL', '/aamarpay/fail'),
    'cancel_url'  => env('AAMARPAY_CANCEL_URL', '/aamarpay/cancel'),

    /*
    |--------------------------------------------------------------------------
    | Transaction Database Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, transactions and callback payloads are automatically logged
    | in the `aamarpay_transactions` database table.
    |
    */
    'log_transactions' => true,
];
