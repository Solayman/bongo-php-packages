<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Monitor Configuration
    |--------------------------------------------------------------------------
    */
    'path' => env('QUEUE_MONITOR_PATH', '/queue-monitor'),

    'middleware' => ['auth'],

    'refresh_interval' => (int) env('QUEUE_MONITOR_REFRESH', 5),
];
