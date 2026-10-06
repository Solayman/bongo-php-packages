<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | GeoIP Configuration
    |--------------------------------------------------------------------------
    | Default location for localhost/private network IPs, and service settings.
    */

    'default_location' => [
        'ip'           => '127.0.0.1',
        'country'      => 'Bangladesh',
        'country_code' => 'BD',
        'city'         => 'Dhaka',
        'region'       => 'Dhaka Division',
        'timezone'     => 'Asia/Dhaka',
        'currency'     => 'BDT',
        'latitude'     => 23.8103,
        'longitude'    => 90.4125,
        'is_local'     => true,
    ],

    // HTTP timeout for external IP resolution in seconds
    'timeout' => 5,

    // Cache driver or enable caching
    'cache' => true,
];
