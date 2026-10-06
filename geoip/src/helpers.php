<?php

declare(strict_types=1);

use Bongo\Packages\GeoIP\GeoIP;
use Bongo\Packages\GeoIP\Location;

if (!function_exists('geoip')) {
    /**
     * Resolve IP address location or get client location.
     */
    function geoip(?string $ip = null): Location
    {
        return GeoIP::lookup($ip);
    }
}
