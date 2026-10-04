<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Image Processing Driver
    |--------------------------------------------------------------------------
    |
    | Bongo's image package is powered by PHP's native ext-gd extension,
    | ensuring high-performance image manipulation with zero external
    | composer dependencies.
    |
    */
    'driver' => 'gd',

    /*
    |--------------------------------------------------------------------------
    | Default Encoding Quality
    |--------------------------------------------------------------------------
    |
    | The default compression quality (0-100) applied when exporting or
    | saving images as WebP or JPEG.
    |
    */
    'quality' => (int) env('IMAGE_QUALITY', 85),

    /*
    |--------------------------------------------------------------------------
    | Watermark Defaults
    |--------------------------------------------------------------------------
    |
    | Default watermark configuration when using automatic watermarking.
    |
    */
    'watermark' => [
        'path' => env('IMAGE_WATERMARK_PATH', null),
        'position' => env('IMAGE_WATERMARK_POSITION', 'bottom-right'),
        'opacity' => (int) env('IMAGE_WATERMARK_OPACITY', 80),
        'offset_x' => (int) env('IMAGE_WATERMARK_OFFSET_X', 15),
        'offset_y' => (int) env('IMAGE_WATERMARK_OFFSET_Y', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Preset Thumbnail Sizes
    |--------------------------------------------------------------------------
    |
    | Common preset dimensions for responsive responsive image generation.
    |
    */
    'presets' => [
        'thumb' => ['width' => 150, 'height' => 150],
        'medium' => ['width' => 600, 'height' => 400],
        'large' => ['width' => 1200, 'height' => 800],
        'avatar' => ['width' => 200, 'height' => 200],
    ],
];
