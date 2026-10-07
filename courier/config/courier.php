<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Courier Service
    |--------------------------------------------------------------------------
    |
    | Supported drivers: 'steadfast', 'pathao'
    |
    */
    'default' => env('COURIER_DRIVER', 'steadfast'),

    /*
    | Steadfast Courier API Configuration
    | Register at: https://steadfast.com.bd
    */
    'steadfast' => [
        'base_url'   => env('STEADFAST_BASE_URL', 'https://portal.steadfast.com.bd/api/v1'),
        'api_key'    => env('STEADFAST_API_KEY', ''),
        'secret_key' => env('STEADFAST_SECRET_KEY', ''),
    ],

    /*
    | Pathao Courier API Configuration
    | Register at: https://merchant.pathao.com
    */
    'pathao' => [
        'base_url'      => env('PATHAO_BASE_URL', 'https://courier-api-sandbox.pathao.com/aladdin/api/v1'),
        'client_id'     => env('PATHAO_CLIENT_ID', ''),
        'client_secret' => env('PATHAO_CLIENT_SECRET', ''),
        'username'      => env('PATHAO_USERNAME', ''),
        'password'      => env('PATHAO_PASSWORD', ''),
        'store_id'      => env('PATHAO_STORE_ID', ''),
    ],

    // Shared secret the courier sends with webhooks (Authorization: Bearer ... or X-Pathao-Signature)
    'webhook_secret' => env('COURIER_WEBHOOK_SECRET', ''),
];
