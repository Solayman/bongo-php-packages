<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayPal Environment Mode
    |--------------------------------------------------------------------------
    |
    | Supported: "sandbox", "live"
    |
    */
    'mode' => env('PAYPAL_MODE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | PayPal REST API Credentials
    |--------------------------------------------------------------------------
    |
    | Obtain Client ID and Secret from the PayPal Developer Dashboard:
    | https://developer.paypal.com/dashboard/applications
    |
    */
    'client_id'     => env('PAYPAL_CLIENT_ID', ''),
    'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | PayPal Webhook ID
    |--------------------------------------------------------------------------
    |
    | Webhook ID generated in PayPal Developer Dashboard for signature validation.
    |
    */
    'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | Default ISO 4217 three-letter currency code (e.g. USD, EUR, GBP, CAD, AUD).
    |
    */
    'currency' => env('PAYPAL_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Brand & Redirect URLs
    |--------------------------------------------------------------------------
    |
    | Return and cancel URLs for the hosted PayPal checkout experience.
    |
    */
    'brand_name'  => env('APP_NAME', 'Bongo Store'),
    'locale'      => env('PAYPAL_LOCALE', 'en-US'),
    'return_url'  => env('PAYPAL_RETURN_URL', '/paypal/success'),
    'cancel_url'  => env('PAYPAL_CANCEL_URL', '/paypal/cancel'),

    /*
    |--------------------------------------------------------------------------
    | Payment Intent
    |--------------------------------------------------------------------------
    |
    | Supported: "CAPTURE" (immediate capture), "AUTHORIZE" (hold funds)
    |
    */
    'intent' => env('PAYPAL_INTENT', 'CAPTURE'),
];
