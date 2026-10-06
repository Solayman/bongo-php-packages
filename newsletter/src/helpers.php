<?php

declare(strict_types=1);

use Bongo\Packages\Newsletter\Newsletter;

if (!function_exists('newsletter')) {
    /**
     * Get the Newsletter instance.
     */
    function newsletter(): Newsletter
    {
        return new Newsletter();
    }
}
