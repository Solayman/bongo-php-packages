<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Newsletter Database Table
    |--------------------------------------------------------------------------
    |
    | The database table used for storing newsletter subscribers.
    |
    */
    'table' => 'newsletter_subscribers',

    /*
    |--------------------------------------------------------------------------
    | Double Opt-In
    |--------------------------------------------------------------------------
    |
    | When enabled, subscribers will receive a verification token and will
    | remain in 'pending' status until verified via link.
    |
    */
    'double_opt_in' => true,

    /*
    |--------------------------------------------------------------------------
    | Verification and Unsubscribe URLs
    |--------------------------------------------------------------------------
    |
    | Route URI templates for verifying and unsubscribing.
    |
    */
    'verify_url' => '/newsletter/verify/{token}',
    'unsubscribe_url' => '/newsletter/unsubscribe/{token}',

    /*
    |--------------------------------------------------------------------------
    | Default Subscription Source
    |--------------------------------------------------------------------------
    */
    'default_source' => 'website',
];
