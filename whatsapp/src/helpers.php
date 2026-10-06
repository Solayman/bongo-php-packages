<?php

declare(strict_types=1);

use Bongo\Packages\WhatsApp\WhatsApp;

if (!function_exists('whatsapp')) {
    /**
     * Get the WhatsApp client instance.
     */
    function whatsapp(?string $token = null, ?string $phoneNumberId = null): WhatsApp
    {
        return WhatsApp::make($token, $phoneNumberId);
    }
}
