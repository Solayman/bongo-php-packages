<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    | Default base currency and standard symbols.
    */
    'default' => env('CURRENCY_DEFAULT', 'BDT'),

    'currencies' => [
        'BDT' => [
            'name'      => 'Bangladeshi Taka',
            'symbol'    => '৳',
            'precision' => 2,
            'format'    => ':symbol:amount',
            'rate'      => 1.0,
        ],
        'USD' => [
            'name'      => 'US Dollar',
            'symbol'    => '$',
            'precision' => 2,
            'format'    => ':symbol:amount',
            'rate'      => 0.0082, // 1 BDT = ~0.0082 USD (or ~121.50 BDT/USD)
        ],
        'EUR' => [
            'name'      => 'Euro',
            'symbol'    => '€',
            'precision' => 2,
            'format'    => ':symbol:amount',
            'rate'      => 0.0076,
        ],
        'GBP' => [
            'name'      => 'British Pound',
            'symbol'    => '£',
            'precision' => 2,
            'format'    => ':symbol:amount',
            'rate'      => 0.0064,
        ],
        'SAR' => [
            'name'      => 'Saudi Riyal',
            'symbol'    => 'SAR ',
            'precision' => 2,
            'format'    => ':amount :symbol',
            'rate'      => 0.031,
        ],
        'AED' => [
            'name'      => 'UAE Dirham',
            'symbol'    => 'AED ',
            'precision' => 2,
            'format'    => ':amount :symbol',
            'rate'      => 0.030,
        ],
        'INR' => [
            'name'      => 'Indian Rupee',
            'symbol'    => '₹',
            'precision' => 2,
            'format'    => ':symbol:amount',
            'rate'      => 0.69,
        ],
    ],

    // Cache duration for live exchange rates in seconds (default: 12 hours)
    'cache_ttl' => 43200,

    // Primary live rates provider: 'frankfurter', 'manual'
    'driver' => env('CURRENCY_DRIVER', 'manual'),
];
