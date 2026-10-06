<?php

declare(strict_types=1);

use Bongo\Packages\GoogleMeet\GoogleMeet;

if (!function_exists('google_meet')) {
    /**
     * Get the Google Meet client instance.
     */
    function google_meet(): GoogleMeet
    {
        return new GoogleMeet();
    }
}
