<?php

declare(strict_types=1);

use Bongo\Packages\Coupon\Coupon;
use Bongo\Packages\Coupon\CouponResult;

if (!function_exists('coupon')) {
    /**
     * Check and evaluate a coupon code or fetch coupon instance.
     */
    function coupon(?string $code = null, ?float $subtotal = null, float $shipping = 0.0): Coupon|CouponResult|null
    {
        if ($code === null) {
            return null;
        }

        if ($subtotal === null) {
            return Coupon::find($code);
        }

        return Coupon::check($code, $subtotal, $shipping);
    }
}
