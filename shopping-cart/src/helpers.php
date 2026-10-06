<?php

declare(strict_types=1);

use Bongo\Packages\ShoppingCart\Cart;

if (!function_exists('cart')) {
    /**
     * Get the shopping cart instance.
     */
    function cart(?string $instance = null): Cart
    {
        return Cart::instance($instance);
    }
}
