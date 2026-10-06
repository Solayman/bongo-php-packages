<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Coupon Configuration
    |--------------------------------------------------------------------------
    | Configure default coupons, expiration policies, and stacking options.
    */
    'allow_stacking' => false,

    // Seed / in-memory promotion codes
    'coupons' => [
        'WELCOME10' => [
            'type'             => 'percentage',
            'value'            => 10.0,
            'min_order_amount' => 500.0,
            'max_discount'     => 200.0,
            'is_active'        => true,
        ],
        'FREESHIP' => [
            'type'             => 'free_shipping',
            'value'            => 0.0,
            'min_order_amount' => 1000.0,
            'is_active'        => true,
        ],
        'BONGO50' => [
            'type'             => 'fixed',
            'value'            => 50.0,
            'min_order_amount' => 300.0,
            'is_active'        => true,
        ],
    ],
];
