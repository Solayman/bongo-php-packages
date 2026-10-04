<?php

declare(strict_types=1);

use Bongo\Packages\ActivityLog\ActivityLogger;

if (!function_exists('activity')) {
    /**
     * Start an activity logging chain.
     */
    function activity(?string $logName = null): ActivityLogger
    {
        return new ActivityLogger($logName);
    }
}
