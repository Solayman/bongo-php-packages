<?php

declare(strict_types=1);

namespace Bongo\Packages\Captcha\Drivers;

use RuntimeException;

class ImageDriver implements CaptchaDriverInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function inputName(): string
    {
        return $this->config['session_key'] ?? 'bongo_captcha_image';
    }

    public function script(): string
    {
        return '';
    }

    /**
     * Generate the random challenge phrase and store in session.
     */
    public function generatePhrase(): string
    {
        $this->ensureSession();

        $length = (int) ($this->config['length'] ?? 5);
        $chars = (string) ($this->config['characters'] ?? '23456789abcdefghkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ');
        $phrase = '';
        $maxIndex = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $phrase .= $chars[random_int(0, $maxIndex)];
        }

        $sessionKey = $this->inputName();
        $_SESSION[$sessionKey] = [
            'phrase'     => strtoupper($phrase),
            'created_at' => time(),
        ];

        return $phrase;
    }

    /**
     * Render the captcha image and input field as HTML.
     */
    public function render(array $attributes = []): string
    {
        $phrase = $this->generatePhrase();
        $base64 = $this->createBase64Image($phrase);

        $inputName = $this->inputName();
        $id = htmlspecialchars($attributes['id'] ?? $inputName, ENT_QUOTES, 'UTF-8');
        $class = htmlspecialchars($attributes['class'] ?? 'bongo-captcha-input', ENT_QUOTES, 'UTF-8');
        $placeholder = htmlspecialchars($attributes['placeholder'] ?? 'Enter code', ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($attributes['label'] ?? 'Security Verification:', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="bongo-captcha-image-wrapper" style="margin-bottom: 1rem;">
    <label for="{$id}" style="display: block; font-weight: 600; margin-bottom: 0.35rem;">{$label}</label>
    <div style="display: flex; align-items: center; gap: 10px;">
        <img src="{$base64}" alt="Security Captcha" style="border: 1px solid #ccc; border-radius: 4px; vertical-align: middle;" />
        <input type="text" name="{$inputName}" id="{$id}" class="{$class}" placeholder="{$placeholder}" required autocomplete="off" style="padding: 0.5rem 0.75rem; border: 1px solid #ccc; border-radius: 4px; width: 140px; text-transform: uppercase;" />
    </div>
</div>
HTML;
    }

    /**
     * Verify the user's submitted input.
     */
    public function verify(?string $response = null, ?string $clientIp = null): bool
    {
        $this->ensureSession();

        $sessionKey = $this->inputName();
        $stored = $_SESSION[$sessionKey] ?? null;

        // Clear immediately to prevent replay
        unset($_SESSION[$sessionKey]);

        if (!is_array($stored) || !isset($stored['phrase'], $stored['created_at'])) {
            return false;
        }

        // Expire after 10 minutes
        if (time() - $stored['created_at'] > 600) {
            return false;
        }

        $userResponse = strtoupper(trim((string) ($response ?? ($_POST[$sessionKey] ?? ''))));
        if ($userResponse === '') {
            return false;
        }

        return hash_equals((string) $stored['phrase'], $userResponse);
    }

    /**
     * Generate base64 data URI representing PNG image.
     */
    public function createBase64Image(string $phrase): string
    {
        if (!extension_loaded('gd')) {
            // Safe SVG fallback if GD is not loaded
            return $this->createSvgDataUri($phrase);
        }

        $width = (int) ($this->config['width'] ?? 150);
        $height = (int) ($this->config['height'] ?? 45);

        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            return $this->createSvgDataUri($phrase);
        }

        // Background color
        $bgColor = imagecolorallocate($image, random_int(240, 255), random_int(240, 255), random_int(240, 255));
        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        // Noise lines
        $noiseLines = (int) ($this->config['noise_lines'] ?? 4);
        for ($i = 0; $i < $noiseLines; $i++) {
            $lineColor = imagecolorallocate($image, random_int(150, 220), random_int(150, 220), random_int(150, 220));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
        }

        // Noise dots
        $noiseDots = (int) ($this->config['noise_dots'] ?? 50);
        for ($i = 0; $i < $noiseDots; $i++) {
            $dotColor = imagecolorallocate($image, random_int(140, 200), random_int(140, 200), random_int(140, 200));
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $dotColor);
        }

        // Text drawing
        $len = strlen($phrase);
        $charWidth = (int) ($width / ($len + 1));
        for ($i = 0; $i < $len; $i++) {
            $textColor = imagecolorallocate($image, random_int(20, 100), random_int(20, 100), random_int(20, 100));
            $x = ($i * $charWidth) + random_int(10, 18);
            $y = random_int(10, 20);
            imagestring($image, 5, $x, $y, $phrase[$i], $textColor);
        }

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode((string) $contents);
    }

    /**
     * Fallback clean SVG data URI if GD is unavailable.
     */
    protected function createSvgDataUri(string $phrase): string
    {
        $width = (int) ($this->config['width'] ?? 150);
        $height = (int) ($this->config['height'] ?? 45);
        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\">";
        $svg .= "<rect width=\"100%\" height=\"100%\" fill=\"#f3f4f6\" rx=\"4\"/>";
        $svg .= "<text x=\"50%\" y=\"58%\" dominant-baseline=\"middle\" text-anchor=\"middle\" font-family=\"monospace\" font-size=\"22\" font-weight=\"bold\" fill=\"#1f2937\" letter-spacing=\"5\">{$phrase}</text>";
        $svg .= "</svg>";

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    protected function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }
}
