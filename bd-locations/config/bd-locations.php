<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bangladesh Locations Configuration
    |--------------------------------------------------------------------------
    | Default locale: 'en' (English) or 'bn' (Bengali)
    */
    'default_locale' => env('BD_LOCATIONS_LOCALE', 'en'),

    /*
    | Shipping fees by zone (optional defaults for store integration)
    */
    'shipping_rates' => [
        'inside_dhaka'  => (float) env('SHIPPING_INSIDE_DHAKA', 60.0),
        'dhaka_suburb'  => (float) env('SHIPPING_DHAKA_SUBURB', 100.0),
        'outside_dhaka' => (float) env('SHIPPING_OUTSIDE_DHAKA', 120.0),
    ],
];
