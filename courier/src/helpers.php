<?php

declare(strict_types=1);

use Bongo\Packages\Courier\Courier;

if (!function_exists('courier')) {
    /**
     * Get the Courier manager or driver instance.
     */
    function courier(?string $driver = null): Courier
    {
        return new Courier($driver);
    }
}
