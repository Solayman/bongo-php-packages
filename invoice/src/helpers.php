<?php

declare(strict_types=1);

use Bongo\Packages\Invoice\Invoice;

if (!function_exists('invoice')) {
    /**
     * Create a new fluent Invoice builder.
     */
    function invoice(?string $number = null): Invoice
    {
        return Invoice::make($number);
    }
}
