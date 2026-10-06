<?php

use Bongo\Packages\BdLocations\Location;

if (!function_exists('bd_divisions')) {
    function bd_divisions(): array
    {
        return Location::divisions();
    }
}

if (!function_exists('bd_districts')) {
    function bd_districts(?int $divisionId = null): array
    {
        return Location::districts($divisionId);
    }
}

if (!function_exists('bd_is_inside_dhaka')) {
    function bd_is_inside_dhaka(int|string $district): bool
    {
        return Location::isInsideDhaka($district);
    }
}
