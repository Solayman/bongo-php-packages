<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Impersonate Configuration
    |--------------------------------------------------------------------------
    */
    'redirect_to' => env('IMPERSONATE_REDIRECT_TO', '/dashboard'),

    'leave_redirect_to' => env('IMPERSONATE_LEAVE_REDIRECT_TO', '/dashboard'),

    'session_key' => '_bongo_impersonator_id',

    // Guard used by the /impersonate routes (never taken from the request)
    'guard' => env('IMPERSONATE_GUARD', 'web'),
];
