<?php
declare(strict_types=1);

use Bongo\Packages\Toast\Toast;

if (!function_exists('toast')) {
    /**
     * Flash a toast message or get the Toast manager.
     */
    function toast(?string $message = null, string $type = 'info', ?string $title = null, array $options = []): Toast
    {
        if ($message !== null) {
            Toast::add($type, $message, $title, $options);
        }
        return new Toast();
    }
}
