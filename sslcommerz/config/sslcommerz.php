<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SSLCommerz Credentials
    |--------------------------------------------------------------------------
    |
    | Get your Store ID and Store Password from the SSLCommerz merchant panel.
    | Set SSLCOMMERZ_SANDBOX=true for sandbox testing, false for live payments.
    |
    */
    'store_id' => env('SSLCOMMERZ_STORE_ID', ''),
    'store_password' => env('SSLCOMMERZ_STORE_PASSWORD', ''),
    'sandbox' => (bool) env('SSLCOMMERZ_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Default Transaction Currency
    |--------------------------------------------------------------------------
    |
    | Supported currencies: BDT, USD, EUR, GBP, etc.
    |
    */
    'currency' => env('SSLCOMMERZ_CURRENCY', 'BDT'),

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    |
    | URLs where SSLCommerz will POST the transaction results.
    | Can be relative application paths or absolute URLs.
    |
    */
    'success_url' => env('SSLCOMMERZ_SUCCESS_URL', '/sslcommerz/success'),
    'fail_url' => env('SSLCOMMERZ_FAIL_URL', '/sslcommerz/fail'),
    'cancel_url' => env('SSLCOMMERZ_CANCEL_URL', '/sslcommerz/cancel'),
    'ipn_url' => env('SSLCOMMERZ_IPN_URL', '/sslcommerz/ipn'),

    /*
    |--------------------------------------------------------------------------
    | Database Transaction Logging
    |--------------------------------------------------------------------------
    |
    | If enabled, payments and callbacks are automatically logged into the
    | `sslcommerz_transactions` table.
    |
    */
    'log_transactions' => true,
];
