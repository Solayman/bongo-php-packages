<?php

declare(strict_types=1);

use Bongo\Packages\Honeypot\Honeypot;

if (!function_exists('honeypot')) {
    /**
     * Get the Honeypot instance or render invisible form protection fields.
     */
    function honeypot(): Honeypot
    {
        return Honeypot::instance();
    }
}
