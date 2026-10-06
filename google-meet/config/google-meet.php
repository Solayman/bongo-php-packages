<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Service Account Credentials
    |--------------------------------------------------------------------------
    |
    | Provide either direct credentials or a path to service-account.json.
    | Enable Google Calendar API in Google Cloud Console.
    |
    */
    'client_email' => env('GOOGLE_SERVICE_ACCOUNT_EMAIL', ''),
    'private_key'  => env('GOOGLE_SERVICE_ACCOUNT_PRIVATE_KEY', ''),
    'key_file'     => env('GOOGLE_SERVICE_ACCOUNT_KEY_FILE', ''),

    /*
    | Primary Calendar ID to create meetings on (usually 'primary' or specific calendar email)
    */
    'calendar_id'  => env('GOOGLE_CALENDAR_ID', 'primary'),

    /*
    | Default timezone for events
    */
    'default_timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    | Default meeting duration in minutes
    */
    'default_duration' => 45,
];
