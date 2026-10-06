<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Push Notifications Configuration
    |--------------------------------------------------------------------------
    | Configure Web Push (VAPID) and Firebase Cloud Messaging (FCM v1).
    */

    'default' => env('PUSH_DRIVER', 'webpush'),

    'webpush' => [
        'subject'     => env('WEBPUSH_SUBJECT', 'mailto:admin@example.com'),
        'public_key'  => env('WEBPUSH_PUBLIC_KEY', ''),
        'private_key' => env('WEBPUSH_PRIVATE_KEY', ''),
        'ttl'         => 86400, // 24 hours
        'urgency'     => 'normal', // very-low, low, normal, high
    ],

    'fcm' => [
        'project_id'          => env('FCM_PROJECT_ID', ''),
        'client_email'        => env('FCM_CLIENT_EMAIL', ''),
        'private_key'         => env('FCM_PRIVATE_KEY', ''),
    ],
];
