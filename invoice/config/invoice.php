<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Invoice Configuration
    |--------------------------------------------------------------------------
    */
    'currency'        => env('INVOICE_CURRENCY', 'BDT'),
    'currency_symbol' => env('INVOICE_CURRENCY_SYMBOL', '৳'),

    'tax_rate'        => 0.0, // default tax percentage (e.g. 5.0)

    'company' => [
        'name'       => env('APP_NAME', 'Bongo Store'),
        'address'    => 'Dhaka, Bangladesh',
        'phone'      => '+880 1700-000000',
        'email'      => 'billing@example.com',
        'vat_number' => '',
    ],

    'thermal_pos' => [
        'width_mm'   => 80, // 80mm or 58mm
        'font_size'  => '12px',
    ],
];
