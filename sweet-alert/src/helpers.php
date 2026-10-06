<?php

declare(strict_types=1);

use Bongo\Packages\SweetAlert\SweetAlert;

if (!function_exists('sweet_alert')) {
    /**
     * Flash an alert or get the SweetAlert manager.
     */
    function sweet_alert(?string $title = null, string $text = '', string $icon = 'info', array $options = []): SweetAlert
    {
        if ($title !== null) {
            SweetAlert::alert($title, $text, $icon, $options);
        }
        return new SweetAlert();
    }
}

if (!function_exists('confirm_dialog')) {
    /**
     * Queue a confirmation dialog.
     */
    function confirm_dialog(string $title, string $text, array $options = []): SweetAlert
    {
        return SweetAlert::confirm($title, $text, $options);
    }
}
