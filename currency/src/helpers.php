<?php

declare(strict_types=1);

use Bongo\Packages\Currency\Currency;
use Bongo\Packages\Currency\CurrencyConverter;

if (!function_exists('currency')) {
    /**
     * Fluent currency converter helper, or static formatter.
     */
    function currency(?float $amount = null, ?string $currency = null): CurrencyConverter|string
    {
        if ($amount === null) {
            return Currency::getDefault();
        }

        return CurrencyConverter::of($amount, $currency);
    }
}

if (!function_exists('money_format_bongo')) {
    /**
     * Formats an amount using currency symbol and rules.
     */
    function money_format_bongo(float $amount, ?string $currency = null): string
    {
        return Currency::format($amount, $currency);
    }
}
