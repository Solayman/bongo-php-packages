<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API Configuration (Meta Graph API)
    |--------------------------------------------------------------------------
    | Configure Meta WhatsApp Cloud API credentials. Get these from your
    | Meta Developer App Dashboard -> WhatsApp -> API Setup.
    */
    'token' => env('WHATSAPP_TOKEN', ''),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),
    'api_version' => env('WHATSAPP_API_VERSION', 'v20.0'),
    'timeout' => 15,
];
