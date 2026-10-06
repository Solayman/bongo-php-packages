<?php

declare(strict_types=1);

namespace Bongo\Packages\Image;

use Bongo\Packages\Image\Exceptions\ImageException;

/**
 * Intervention Image style ImageManager.
 */
class ImageManager
{
    protected string $driver;

    public function __construct(string $driver = 'gd')
    {
        $this->driver = $driver;
    }

    public static function gd(): static
    {
        return new static('gd');
    }

    public static function imagick(): static
    {
        return new static('imagick');
    }

    /**
     * Read an image from any source (file, data-uri, binary, UploadedFile, resource).
     */
    public function read(mixed $source): Image
    {
        return Image::read($source);
    }

    /**
     * Alias for read (Intervention Image v2 compatibility).
     */
    public function make(mixed $source): Image
    {
        return Image::make($source);
    }

    /**
     * Create a new blank canvas.
     */
    public function create(int $width, int $height, string|array $bgColor = 'transparent'): Image
    {
        return Image::create($width, $height, $bgColor);
    }

    /**
     * Alias for create.
     */
    public function canvas(int $width, int $height, string|array $bgColor = 'transparent'): Image
    {
        return Image::canvas($width, $height, $bgColor);
    }
}
