<?php

declare(strict_types=1);

use Bongo\Packages\Zoom\Zoom;

if (!function_exists('zoom')) {
    /**
     * Get the Zoom client instance.
     */
    function zoom(): Zoom
    {
        return new Zoom();
    }
}
