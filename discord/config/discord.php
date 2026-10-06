<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Discord Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook_url' => env('DISCORD_WEBHOOK_URL', ''),

    'username' => env('DISCORD_USERNAME', 'Bongo Bot'),

    'avatar_url' => env('DISCORD_AVATAR_URL', ''),

    'timeout' => (int) env('DISCORD_TIMEOUT', 5),
];
