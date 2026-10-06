<?php

declare(strict_types=1);

use Bongo\Packages\DeviceDetector\Device;

if (!function_exists('device')) {
    /**
     * Get the device detector instance for the current or specified User-Agent.
     */
    function device(?string $userAgent = null): Device
    {
        return Device::detect($userAgent);
    }
}
