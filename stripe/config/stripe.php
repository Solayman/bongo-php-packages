<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe API Credentials
    |--------------------------------------------------------------------------
    |
    | Get your Publishable and Secret API keys from the Stripe Dashboard:
    | https://dashboard.stripe.com/apikeys
    |
    */
    'key' => env('STRIPE_KEY', ''),
    'secret' => env('STRIPE_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Used to cryptographically verify incoming webhook payloads:
    | https://dashboard.stripe.com/webhooks
    |
    */
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | Default 3-letter ISO currency code (usd, eur, gbp, bdt, etc.).
    |
    */
    'currency' => env('STRIPE_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs
    |--------------------------------------------------------------------------
    |
    | Fallback return URLs after customer completes or cancels Checkout.
    |
    */
    'success_url' => env('STRIPE_SUCCESS_URL', env('APP_URL', 'http://127.0.0.1:8000') . '/stripe/success?session_id={CHECKOUT_SESSION_ID}'),
    'cancel_url'  => env('STRIPE_CANCEL_URL', env('APP_URL', 'http://127.0.0.1:8000') . '/stripe/cancel'),

    // POST /stripe/checkout takes amount and order_id from the browser: demo/testing only
    'demo_checkout' => (bool) env('STRIPE_DEMO_CHECKOUT', false),
];
