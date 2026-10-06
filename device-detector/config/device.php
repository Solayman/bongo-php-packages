<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Device Detector Configuration
    |--------------------------------------------------------------------------
    */
    // Additional custom bot signatures to detect
    'custom_bots' => [
        // 'CustomSpider',
    ],

    // Default device type fallback if UA is completely missing or empty
    'default_device' => 'desktop',
];
