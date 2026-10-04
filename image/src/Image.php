<?php

declare(strict_types=1);

namespace Bongo\Packages\Image;

use Bongo\Packages\Image\Exceptions\ImageException;
use GdImage;

class Image
{
    protected ?GdImage $image = null;
    protected ?string $sourcePath = null;
    protected string $mimeType = 'image/png';

    public function __construct(?GdImage $image = null, ?string $sourcePath = null, string $mimeType = 'image/png')
    {
        $this->image = $image;
        $this->sourcePath = $sourcePath;
        $this->mimeType = $mimeType;
    }

    public function __destruct()
    {
        $this->destroy();
    }

    /**
     * Create a new blank canvas with background color.
     */
    public static function create(int $width, int $height, string|array $bgColor = 'transparent'): static
    {
        if ($width <= 0 || $height <= 0) {
            throw new ImageException("Width and height must be positive integers, got {$width}x{$height}.");
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            throw new ImageException("Failed to allocate {$width}x{$height} GD image canvas.");
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $color = static::allocateColor($canvas, $bgColor);
        imagefill($canvas, 0, 0, $color);
        imagealphablending($canvas, true);

        return new static($canvas, null, 'image/png');
    }

    /**
     * Alias for create.
     */
    public static function canvas(int $width, int $height, string|array $bgColor = 'transparent'): static
    {
        return static::create($width, $height, $bgColor);
    }

    /**
     * Load an image from a file path.
     */
    public static function load(string $path): static
    {
        if (!file_exists($path)) {
            throw new ImageException("Image file not found: {$path}");
        }

        if (!is_readable($path)) {
            throw new ImageException("Image file is not readable: {$path}");
        }

        $info = @getimagesize($path);
        if ($info === false) {
            throw new ImageException("Unable to read image dimensions or unsupported format: {$path}");
        }

        $mime = $info['mime'] ?? 'image/jpeg';
        $type = $info[2] ?? 0;

        $im = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : false,
            default => false,
        };

        if ($im === false) {
            throw new ImageException("Failed to create image resource from [{$path}] of type [{$mime}].");
        }

        imagealphablending($im, false);
        imagesavealpha($im, true);

        return new static($im, $path, $mime);
    }

    /**
     * Load an image from binary string data.
     */
    public static function fromBinary(string $data): static
    {
        if ($data === '') {
            throw new ImageException("Cannot load image from empty binary string.");
        }

        $im = @imagecreatefromstring($data);
        if ($im === false) {
            throw new ImageException("Failed to load image from binary string.");
        }

        imagealphablending($im, false);
        imagesavealpha($im, true);

        $mime = 'image/png';
        if (str_starts_with($data, "\xFF\xD8\xFF")) {
            $mime = 'image/jpeg';
        } elseif (str_starts_with($data, "\x89PNG\r\n\x1a\n")) {
            $mime = 'image/png';
        } elseif (str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a')) {
            $mime = 'image/gif';
        } elseif (str_starts_with($data, 'RIFF') && str_contains(substr($data, 8, 8), 'WEBP')) {
            $mime = 'image/webp';
        }

        return new static($im, null, $mime);
    }

    public function width(): int
    {
        $this->ensureLoaded();
        return imagesx($this->image);
    }

    public function height(): int
    {
        $this->ensureLoaded();
        return imagesy($this->image);
    }

    public function mime(): string
    {
        return $this->mimeType;
    }

    public function aspectRatio(): float
    {
        $h = $this->height();
        return $h > 0 ? (float) ($this->width() / $h) : 1.0;
    }

    public function isLandscape(): bool
    {
        return $this->width() > $this->height();
    }

    public function isPortrait(): bool
    {
        return $this->height() > $this->width();
    }

    public function isSquare(): bool
    {
        return $this->width() === $this->height();
    }

    public function resource(): ?GdImage
    {
        return $this->image;
    }

    /**
     * Resize image to specified dimensions.
     */
    public function resize(int $width, int $height, bool $preserveAspect = false): static
    {
        $this->ensureLoaded();

        if ($width <= 0 || $height <= 0) {
            throw new ImageException("Target dimensions must be positive integers.");
        }

        $origW = $this->width();
        $origH = $this->height();

        if ($preserveAspect) {
            $ratio = min($width / $origW, $height / $origH);
            $targetW = max(1, (int) round($origW * $ratio));
            $targetH = max(1, (int) round($origH * $ratio));
        } else {
            $targetW = $width;
            $targetH = $height;
        }

        $resized = imagecreatetruecolor($targetW, $targetH);
        if ($resized === false) {
            throw new ImageException("Failed to allocate resized canvas.");
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);

        imagecopyresampled(
            $resized,
            $this->image,
            0,
            0,
            0,
            0,
            $targetW,
            $targetH,
            $origW,
            $origH
        );

        $this->replaceImage($resized);
        return $this;
    }

    /**
     * Exact resize ignoring aspect ratio.
     */
    public function resizeExact(int $width, int $height): static
    {
        return $this->resize($width, $height, false);
    }

    /**
     * Resize to specific width, calculating height automatically.
     */
    public function resizeToWidth(int $width): static
    {
        $origW = $this->width();
        $origH = $this->height();
        $height = (int) round(($width / $origW) * $origH);
        return $this->resize($width, max(1, $height), false);
    }

    /**
     * Resize to specific height, calculating width automatically.
     */
    public function resizeToHeight(int $height): static
    {
        $origW = $this->width();
        $origH = $this->height();
        $width = (int) round(($height / $origH) * $origW);
        return $this->resize(max(1, $width), $height, false);
    }

    /**
     * Scale image by a multiplier ratio (e.g. 0.5 for 50%, 2.0 for 200%).
     */
    public function scale(float $ratio): static
    {
        if ($ratio <= 0.0) {
            throw new ImageException("Scale ratio must be greater than 0.");
        }

        $w = max(1, (int) round($this->width() * $ratio));
        $h = max(1, (int) round($this->height() * $ratio));
        return $this->resize($w, $h, false);
    }

    /**
     * Fit image within maximum bounding box, maintaining aspect ratio.
     */
    public function fit(int $maxWidth, int $maxHeight): static
    {
        return $this->resize($maxWidth, $maxHeight, true);
    }

    /**
     * Crop image to width and height. If x or y is null, center crop.
     */
    public function crop(int $width, int $height, ?int $x = null, ?int $y = null): static
    {
        $this->ensureLoaded();

        $origW = $this->width();
        $origH = $this->height();

        $x ??= max(0, (int) round(($origW - $width) / 2));
        $y ??= max(0, (int) round(($origH - $height) / 2));

        $cropped = imagecrop($this->image, [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ]);

        if ($cropped === false) {
            // Fallback manual crop if imagecrop fails
            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);

            imagecopy($canvas, $this->image, 0, 0, $x, $y, $width, $height);
            $cropped = $canvas;
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);

        $this->replaceImage($cropped);
        return $this;
    }

    /**
     * Cover dimensions: scale to fill and center-crop to exact dimensions.
     */
    public function cover(int $width, int $height): static
    {
        $this->ensureLoaded();

        $origW = $this->width();
        $origH = $this->height();

        $ratioW = $width / $origW;
        $ratioH = $height / $origH;
        $scale = max($ratioW, $ratioH);

        $scaledW = (int) round($origW * $scale);
        $scaledH = (int) round($origH * $scale);

        $this->resize($scaledW, $scaledH, false);
        $this->crop($width, $height);

        return $this;
    }

    /**
     * Create a thumbnail of specific dimensions (defaults to square).
     */
    public function thumbnail(int $width, ?int $height = null): static
    {
        return $this->cover($width, $height ?? $width);
    }

    /**
     * Rotate the image by clockwise degrees.
     */
    public function rotate(float $angle, string|array $bgColor = 'transparent'): static
    {
        $this->ensureLoaded();

        $color = static::allocateColor($this->image, $bgColor);
        // Note: GD imagerotate is counter-clockwise, so negative angle rotates clockwise
        $rotated = imagerotate($this->image, -$angle, $color);

        if ($rotated === false) {
            throw new ImageException("Failed to rotate image by {$angle} degrees.");
        }

        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        $this->replaceImage($rotated);
        return $this;
    }

    /**
     * Flip image horizontally.
     */
    public function flipHorizontal(): static
    {
        $this->ensureLoaded();
        imageflip($this->image, IMG_FLIP_HORIZONTAL);
        return $this;
    }

    /**
     * Flip image vertically.
     */
    public function flipVertical(): static
    {
        $this->ensureLoaded();
        imageflip($this->image, IMG_FLIP_VERTICAL);
        return $this;
    }

    /**
     * Flip image both horizontally and vertically.
     */
    public function flipBoth(): static
    {
        $this->ensureLoaded();
        imageflip($this->image, IMG_FLIP_BOTH);
        return $this;
    }

    /**
     * Convert to grayscale.
     */
    public function greyscale(): static
    {
        $this->ensureLoaded();
        imagefilter($this->image, IMG_FILTER_GRAYSCALE);
        return $this;
    }

    /**
     * Apply sepia tone.
     */
    public function sepia(): static
    {
        $this->ensureLoaded();
        imagefilter($this->image, IMG_FILTER_GRAYSCALE);
        imagefilter($this->image, IMG_FILTER_COLORIZE, 90, 60, 40);
        return $this;
    }

    /**
     * Invert image colors (negative).
     */
    public function invert(): static
    {
        $this->ensureLoaded();
        imagefilter($this->image, IMG_FILTER_NEGATE);
        return $this;
    }

    /**
     * Adjust brightness (-255 to +255).
     */
    public function brightness(int $level): static
    {
        $this->ensureLoaded();
        $clamped = max(-255, min(255, $level));
        imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $clamped);
        return $this;
    }

    /**
     * Adjust contrast (-100 to +100).
     */
    public function contrast(int $level): static
    {
        $this->ensureLoaded();
        $clamped = max(-100, min(100, $level));
        // GD uses negative values for more contrast
        imagefilter($this->image, IMG_FILTER_CONTRAST, -$clamped);
        return $this;
    }

    /**
     * Apply Gaussian blur.
     */
    public function blur(int $passes = 1): static
    {
        $this->ensureLoaded();
        $passes = max(1, min(20, $passes));
        for ($i = 0; $i < $passes; $i++) {
            imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
        }
        return $this;
    }

    /**
     * Sharpen image.
     */
    public function sharpen(): static
    {
        $this->ensureLoaded();
        imagefilter($this->image, IMG_FILTER_MEAN_REMOVAL);
        return $this;
    }

    /**
     * Overlay another image on top of this image.
     */
    public function overlay(self|string $overlay, int $x = 0, int $y = 0, int $opacity = 100): static
    {
        $this->ensureLoaded();

        $overlayImg = is_string($overlay) ? static::load($overlay) : $overlay;
        $wmRes = $overlayImg->resource();
        if ($wmRes === null) {
            throw new ImageException("Overlay image resource is invalid.");
        }

        $wmW = $overlayImg->width();
        $wmH = $overlayImg->height();
        $opacity = max(0, min(100, $opacity));

        if ($opacity === 100) {
            imagealphablending($this->image, true);
            imagecopy($this->image, $wmRes, $x, $y, 0, 0, $wmW, $wmH);
        } else {
            // Safe alpha merge preserving transparency
            $cut = imagecreatetruecolor($wmW, $wmH);
            imagealphablending($cut, false);
            imagesavealpha($cut, true);

            imagecopy($cut, $this->image, 0, 0, $x, $y, $wmW, $wmH);
            imagecopy($cut, $wmRes, 0, 0, 0, 0, $wmW, $wmH);

            imagecopymerge($this->image, $cut, $x, $y, 0, 0, $wmW, $wmH, $opacity);
            imagedestroy($cut);
        }

        return $this;
    }

    /**
     * Apply a watermark positioned on the canvas.
     * Positions: 'top-left', 'top-center', 'top-right',
     *            'center-left', 'center', 'center-right',
     *            'bottom-left', 'bottom-center', 'bottom-right'
     */
    public function watermark(
        self|string $watermark,
        string $position = 'bottom-right',
        int $opacity = 80,
        int $offsetX = 10,
        int $offsetY = 10
    ): static {
        $this->ensureLoaded();

        $wm = is_string($watermark) ? static::load($watermark) : $watermark;
        $wmW = $wm->width();
        $wmH = $wm->height();
        $canvasW = $this->width();
        $canvasH = $this->height();

        $x = match ($position) {
            'top-left', 'center-left', 'bottom-left' => $offsetX,
            'top-center', 'center', 'bottom-center' => (int) round(($canvasW - $wmW) / 2),
            default => $canvasW - $wmW - $offsetX, // top-right, center-right, bottom-right
        };

        $y = match ($position) {
            'top-left', 'top-center', 'top-right' => $offsetY,
            'center-left', 'center', 'center-right' => (int) round(($canvasH - $wmH) / 2),
            default => $canvasH - $wmH - $offsetY, // bottom-left, bottom-center, bottom-right
        };

        return $this->overlay($wm, $x, $y, $opacity);
    }

    /**
     * Write text onto the image.
     */
    public function text(string $text, int $x, int $y, array $options = []): static
    {
        $this->ensureLoaded();

        $color = static::allocateColor($this->image, $options['color'] ?? '#ffffff');
        $font = $options['font'] ?? null;
        $size = (int) ($options['size'] ?? 5); // 1-5 for built-in font
        $angle = (int) ($options['angle'] ?? 0);

        imagealphablending($this->image, true);

        if ($font !== null && file_exists($font) && function_exists('imagettftext')) {
            imagettftext($this->image, $size, $angle, $x, $y, $color, $font, $text);
        } else {
            imagestring($this->image, max(1, min(5, $size)), $x, $y, $text, $color);
        }

        return $this;
    }

    /**
     * Encode current image into binary string.
     */
    public function encode(string $format = 'webp', int $quality = 85): string
    {
        $this->ensureLoaded();

        $format = strtolower($format);
        ob_start();

        match ($format) {
            'webp' => imagewebp($this->image, null, max(0, min(100, $quality))),
            'jpg', 'jpeg' => imagejpeg($this->image, null, max(0, min(100, $quality))),
            'png' => imagepng($this->image, null, (int) round((100 - max(0, min(100, $quality))) / 10)),
            'gif' => imagegif($this->image),
            'bmp' => function_exists('imagebmp') ? imagebmp($this->image) : imagepng($this->image),
            default => imagewebp($this->image, null, $quality),
        };

        $output = ob_get_clean();
        if ($output === false) {
            throw new ImageException("Failed to encode image to format [{$format}].");
        }

        return $output;
    }

    public function toWebp(int $quality = 85): string
    {
        return $this->encode('webp', $quality);
    }

    public function toJpeg(int $quality = 85): string
    {
        return $this->encode('jpeg', $quality);
    }

    public function toPng(int $quality = 90): string
    {
        return $this->encode('png', $quality);
    }

    public function toGif(): string
    {
        return $this->encode('gif');
    }

    /**
     * Generate data URI string (e.g. data:image/webp;base64,...).
     */
    public function dataUri(string $format = 'webp', int $quality = 85): string
    {
        $binary = $this->encode($format, $quality);
        $mime = match (strtolower($format)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            default => 'image/webp',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    /**
     * Save the image to a file path.
     */
    public function save(?string $path = null, int $quality = 85, ?string $format = null): static
    {
        $this->ensureLoaded();

        $target = $path ?? $this->sourcePath;
        if ($target === null) {
            throw new ImageException("No destination path specified for saving image.");
        }

        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($format === null) {
            $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
            $format = match ($ext) {
                'jpg', 'jpeg' => 'jpeg',
                'png' => 'png',
                'gif' => 'gif',
                'bmp' => 'bmp',
                'webp' => 'webp',
                default => 'webp',
            };
        }

        $data = $this->encode($format, $quality);
        $res = file_put_contents($target, $data);
        if ($res === false) {
            throw new ImageException("Failed to write image data to [{$target}].");
        }

        $this->sourcePath = $target;
        return $this;
    }

    /**
     * Explicitly destroy the GD image resource.
     */
    public function destroy(): void
    {
        if ($this->image instanceof GdImage) {
            imagedestroy($this->image);
            $this->image = null;
        }
    }

    protected function ensureLoaded(): void
    {
        if ($this->image === null) {
            throw new ImageException("No image resource is loaded.");
        }
    }

    protected function replaceImage(GdImage $newImage): void
    {
        if ($this->image instanceof GdImage && $this->image !== $newImage) {
            imagedestroy($this->image);
        }
        $this->image = $newImage;
    }

    /**
     * Allocate color on GD image from hex string, named color, or [r, g, b, a] array.
     */
    public static function allocateColor(GdImage $image, string|array $color): int
    {
        if (is_array($color)) {
            $r = (int) ($color[0] ?? 0);
            $g = (int) ($color[1] ?? 0);
            $b = (int) ($color[2] ?? 0);
            $alpha = (int) ($color[3] ?? 0);
            return imagecolorallocatealpha($image, $r, $g, $b, $alpha);
        }

        $c = strtolower(trim($color));

        if ($c === 'transparent') {
            return imagecolorallocatealpha($image, 0, 0, 0, 127);
        }

        $named = [
            'black' => [0, 0, 0, 0],
            'white' => [255, 255, 255, 0],
            'red' => [255, 0, 0, 0],
            'green' => [0, 255, 0, 0],
            'blue' => [0, 0, 255, 0],
            'yellow' => [255, 255, 0, 0],
            'cyan' => [0, 255, 255, 0],
            'magenta' => [255, 0, 255, 0],
            'gray' => [128, 128, 128, 0],
            'grey' => [128, 128, 128, 0],
        ];

        if (isset($named[$c])) {
            [$r, $g, $b, $a] = $named[$c];
            return imagecolorallocatealpha($image, $r, $g, $b, $a);
        }

        if (str_starts_with($c, '#')) {
            $hex = ltrim($c, '#');
            if (strlen($hex) === 3) {
                $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
                $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
                $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
                return imagecolorallocatealpha($image, $r, $g, $b, 0);
            }
            if (strlen($hex) === 6) {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                return imagecolorallocatealpha($image, $r, $g, $b, 0);
            }
            if (strlen($hex) === 8) {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                // Convert 0-255 hex alpha to GD 0-127 alpha
                $hexAlpha = hexdec(substr($hex, 6, 2));
                $alpha = (int) round((255 - $hexAlpha) / 2);
                return imagecolorallocatealpha($image, $r, $g, $b, $alpha);
            }
        }

        return imagecolorallocatealpha($image, 0, 0, 0, 0);
    }
}
