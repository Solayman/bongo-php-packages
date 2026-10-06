<?php

declare(strict_types=1);

use Bongo\Packages\PushNotification\Push;

if (!function_exists('push')) {
    /**
     * Get Push notifications manager instance.
     */
    function push(array $config = []): Push
    {
        return Push::make($config);
    }
}
