<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Toast Configuration
    |--------------------------------------------------------------------------
    |
    | Supported positions: 'top-right', 'top-left', 'top-center',
    |                      'bottom-right', 'bottom-left', 'bottom-center'
    |
    */
    'position' => env('TOAST_POSITION', 'top-right'),

    /*
    | Default display duration in milliseconds before auto-dismiss (0 for persistent).
    */
    'duration' => (int) env('TOAST_DURATION', 4000),

    /*
    | Whether toasts are dismissible via a close button.
    */
    'dismissible' => true,

    /*
    | Whether to show an animated progress bar indicating remaining duration.
    */
    'progress_bar' => true,

    /*
    | Maximum number of active toasts visible at one time.
    */
    'max_toasts' => 5,

    /*
    | Session key used for storing flashed toasts.
    */
    'session_key' => 'bongo_toasts',
];
