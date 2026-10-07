<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zoom Server-to-Server OAuth Credentials
    |--------------------------------------------------------------------------
    |
    | Create a Server-to-Server OAuth App in the Zoom App Marketplace:
    | https://marketplace.zoom.us/
    |
    */
    'account_id'    => env('ZOOM_ACCOUNT_ID', ''),
    'client_id'     => env('ZOOM_CLIENT_ID', ''),
    'client_secret' => env('ZOOM_CLIENT_SECRET', ''),

    /*
    | Secret Token configured under Webhooks/Features in Zoom Marketplace
    */
    'secret_token'  => env('ZOOM_SECRET_TOKEN', ''),

    /*
    | Default user ID for meetings ('me' or user email/id)
    */
    'default_user_id' => env('ZOOM_USER_ID', 'me'),

    /*
    | Default meeting settings
    */
    'default_timezone' => env('APP_TIMEZONE', 'UTC'),
    'default_duration' => 45, // in minutes

    'settings' => [
        'host_video'        => true,
        'participant_video' => true,
        'join_before_host'  => false,
        'mute_upon_entry'   => true,
        'waiting_room'      => true,
        'auto_recording'    => 'none', // 'local' | 'cloud' | 'none'
    ],

    // Webhook timestamps older/newer than this many seconds are refused (replay protection)
    'webhook_tolerance' => (int) env('ZOOM_WEBHOOK_TOLERANCE', 300),
];
