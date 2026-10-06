<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default QR Code Settings
    |--------------------------------------------------------------------------
    */
    'default_size' => (int) env('QRCODE_SIZE', 250),
    'default_margin' => (int) env('QRCODE_MARGIN', 2),
    'default_format' => env('QRCODE_FORMAT', 'svg'), // 'svg', 'png', 'data-uri'
    'default_error_correction' => env('QRCODE_ECC', 'M'), // 'L', 'M', 'Q', 'H'

    'foreground_color' => env('QRCODE_FOREGROUND', '#000000'),
    'background_color' => env('QRCODE_BACKGROUND', '#ffffff'),
];
