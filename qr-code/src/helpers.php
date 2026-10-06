<?php

declare(strict_types=1);

use Bongo\Packages\QrCode\QrCode;

if (!function_exists('qr_code')) {
    /**
     * Create a new QR code instance.
     */
    function qr_code(string $text): QrCode
    {
        return QrCode::generate($text);
    }
}
