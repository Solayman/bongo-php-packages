<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Social Login (OAuth 2.0) Configuration
    |--------------------------------------------------------------------------
    |
    | Seamless OAuth authentication for Google, Facebook, and GitHub.
    | Zero external dependencies, pure PHP implementation.
    |
    */

    // Redirect path upon successful login
    'redirect_after_login' => env('SOCIAL_REDIRECT_AFTER_LOGIN', '/'),

    // Target User model class for auto-creating/updating accounts
    'model' => \App\Models\User::class,

    // Automatically create new user accounts if email not found
    'auto_create_user' => true,

    // Sign in to an existing account with the same email, only when the provider verified that email
    'link_by_verified_email' => true,

    'providers' => [
        'google' => [
            'client_id'     => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            'redirect'      => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://127.0.0.1:8000') . '/auth/google/callback'),
            'scopes'        => ['openid', 'profile', 'email'],
        ],

        'facebook' => [
            'client_id'     => env('FACEBOOK_CLIENT_ID', ''),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET', ''),
            'redirect'      => env('FACEBOOK_REDIRECT_URI', env('APP_URL', 'http://127.0.0.1:8000') . '/auth/facebook/callback'),
            'scopes'        => ['email', 'public_profile'],
        ],

        'github' => [
            'client_id'     => env('GITHUB_CLIENT_ID', ''),
            'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
            'redirect'      => env('GITHUB_REDIRECT_URI', env('APP_URL', 'http://127.0.0.1:8000') . '/auth/github/callback'),
            'scopes'        => ['user:email', 'read:user'],
        ],
    ],
];
