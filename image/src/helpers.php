<?php

declare(strict_types=1);

use Bongo\Packages\Image\Image;
use Bongo\Packages\Image\ImageManager;

if (!function_exists('image')) {
    /**
     * Fluent helper for image manipulation (Intervention Image style).
     *
     * Calling image('photo.jpg') reads the image.
     * Calling image() returns the ImageManager.
     */
    function image(mixed $source = null): Image|ImageManager
    {
        if ($source === null) {
            return new ImageManager();
        }

        return Image::read($source);
    }
}
