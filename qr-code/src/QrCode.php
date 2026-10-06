<?php

declare(strict_types=1);

namespace Bongo\Packages\QrCode;

use Bongo\Packages\QrCode\Exceptions\QrCodeException;
use Stringable;

class QrCode implements Stringable
{
    protected string $text;
    protected int $size = 250;
    protected int $margin = 2;
    protected string $foregroundColor = '#000000';
    protected string $backgroundColor = '#ffffff';
    protected int $eccLevel = QrMatrix::ECC_M;

    public function __construct(string $text)
    {
        $this->text = $text;

        if (function_exists('config')) {
            $this->size = (int) config('qrcode.default_size', 250);
            $this->margin = (int) config('qrcode.default_margin', 2);
            $this->foregroundColor = (string) config('qrcode.foreground_color', '#000000');
            $this->backgroundColor = (string) config('qrcode.background_color', '#ffffff');
            $this->eccLevel = static::resolveEcc((string) config('qrcode.default_error_correction', 'M'));
        }
    }

    public static function generate(string $text): static
    {
        return new static($text);
    }

    public static function url(string $url): static
    {
        return static::generate($url);
    }

    public static function wifi(string $ssid, string $password = '', string $encryption = 'WPA', bool $hidden = false): static
    {
        $payload = "WIFI:T:{$encryption};S:{$ssid};P:{$password};" . ($hidden ? 'H:true;' : '') . ';';
        return static::generate($payload);
    }

    public static function bKash(string $merchantNumber, ?float $amount = null, ?string $reference = null): static
    {
        $cleanNumber = preg_replace('/[^\d\+]/', '', $merchantNumber);
        $payload = "bkash://merchant?account={$cleanNumber}";
        if ($amount !== null && $amount > 0) {
            $payload .= "&amount=" . number_format($amount, 2, '.', '');
        }
        if ($reference !== null && $reference !== '') {
            $payload .= "&ref=" . urlencode($reference);
        }
        return static::generate($payload);
    }

    public static function nagad(string $merchantNumber, ?float $amount = null): static
    {
        $cleanNumber = preg_replace('/[^\d\+]/', '', $merchantNumber);
        $payload = "nagad://merchant?account={$cleanNumber}";
        if ($amount !== null && $amount > 0) {
            $payload .= "&amount=" . number_format($amount, 2, '.', '');
        }
        return static::generate($payload);
    }

    public static function email(string $to, string $subject = '', string $body = ''): static
    {
        $payload = "mailto:{$to}";
        $params = [];
        if ($subject !== '') {
            $params[] = 'subject=' . rawurlencode($subject);
        }
        if ($body !== '') {
            $params[] = 'body=' . rawurlencode($body);
        }
        if (!empty($params)) {
            $payload .= '?' . implode('&', $params);
        }
        return static::generate($payload);
    }

    public static function sms(string $number, string $message = ''): static
    {
        $payload = "smsto:{$number}:{$message}";
        return static::generate($payload);
    }

    public static function geo(float $latitude, float $longitude): static
    {
        return static::generate("geo:{$latitude},{$longitude}");
    }

    public function size(int $size): static
    {
        $this->size = max(50, $size);
        return $this;
    }

    public function margin(int $margin): static
    {
        $this->margin = max(0, $margin);
        return $this;
    }

    public function color(string $hexColor): static
    {
        $this->foregroundColor = $hexColor;
        return $this;
    }

    public function backgroundColor(string $hexColor): static
    {
        $this->backgroundColor = $hexColor;
        return $this;
    }

    public function errorCorrection(string|int $level): static
    {
        $this->eccLevel = is_int($level) ? $level : static::resolveEcc($level);
        return $this;
    }

    /**
     * Get the 2D boolean matrix.
     *
     * @return array<int, array<int, bool>>
     */
    public function matrix(): array
    {
        return QrMatrix::create($this->text, $this->eccLevel);
    }

    /**
     * Render as a scalable SVG string.
     */
    public function svg(): string
    {
        $matrix = $this->matrix();
        $matrixSize = count($matrix);
        $totalSize = $matrixSize + ($this->margin * 2);

        $pathParts = [];
        for ($r = 0; $r < $matrixSize; $r++) {
            for ($c = 0; $c < $matrixSize; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $this->margin;
                    $y = $r + $this->margin;
                    $pathParts[] = "M{$x},{$y}h1v1h-1z";
                }
            }
        }

        $pathData = implode('', $pathParts);
        $svgWidth = $this->size;
        $svgHeight = $this->size;
        $bg = htmlspecialchars($this->backgroundColor, ENT_QUOTES, 'UTF-8');
        $fg = htmlspecialchars($this->foregroundColor, ENT_QUOTES, 'UTF-8');

        $bgRect = ($this->backgroundColor !== 'transparent' && $this->backgroundColor !== '')
            ? "<rect width=\"100%\" height=\"100%\" fill=\"{$bg}\"/>"
            : '';

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="{$svgWidth}" height="{$svgHeight}" viewBox="0 0 {$totalSize} {$totalSize}">
    {$bgRect}
    <path fill="{$fg}" d="{$pathData}"/>
</svg>
SVG;
    }

    /**
     * Render as PNG binary data.
     */
    public function png(): string
    {
        if (!extension_loaded('gd')) {
            throw new QrCodeException("GD extension is required to generate raster PNG QR codes.");
        }

        $matrix = $this->matrix();
        $matrixSize = count($matrix);
        $totalCells = $matrixSize + ($this->margin * 2);
        $scale = max(1, (int) floor($this->size / $totalCells));
        $actualSize = $totalCells * $scale;

        $im = imagecreatetruecolor($actualSize, $actualSize);
        if ($im === false) {
            throw new QrCodeException("Failed to allocate GD image for QR code.");
        }

        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Background
        $bg = static::hexToRgb($this->backgroundColor);
        $bgColor = imagecolorallocatealpha($im, $bg[0], $bg[1], $bg[2], $bg[3]);
        imagefill($im, 0, 0, $bgColor);

        // Foreground
        $fg = static::hexToRgb($this->foregroundColor);
        $fgColor = imagecolorallocatealpha($im, $fg[0], $fg[1], $fg[2], $fg[3]);

        for ($r = 0; $r < $matrixSize; $r++) {
            for ($c = 0; $c < $matrixSize; $c++) {
                if ($matrix[$r][$c]) {
                    $x = ($c + $this->margin) * $scale;
                    $y = ($r + $this->margin) * $scale;
                    imagefilledrectangle($im, $x, $y, $x + $scale - 1, $y + $scale - 1, $fgColor);
                }
            }
        }

        ob_start();
        imagepng($im);
        $binary = (string) ob_get_clean();
        imagedestroy($im);

        return $binary;
    }

    /**
     * Render as Base64 Data URI.
     */
    public function dataUri(string $format = 'svg'): string
    {
        if (strtolower($format) === 'png') {
            return 'data:image/png;base64,' . base64_encode($this->png());
        }

        return 'data:image/svg+xml;base64,' . base64_encode($this->svg());
    }

    /**
     * Render as UTF-8 ASCII blocks for terminal / CLI display.
     */
    public function ascii(): string
    {
        $matrix = $this->matrix();
        $matrixSize = count($matrix);
        $lines = [];

        // White border top
        $lines[] = str_repeat('██', $matrixSize + 4);

        for ($r = 0; $r < $matrixSize; $r++) {
            $line = '██'; // Left border
            for ($c = 0; $c < $matrixSize; $c++) {
                $line .= $matrix[$r][$c] ? '  ' : '██';
            }
            $line .= '██'; // Right border
            $lines[] = $line;
        }

        // White border bottom
        $lines[] = str_repeat('██', $matrixSize + 4);

        return implode("\n", $lines) . "\n";
    }

    /**
     * Save QR code to a local file (.svg or .png).
     */
    public function save(string $path): static
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $content = match ($ext) {
            'png' => $this->png(),
            default => $this->svg(),
        };

        if (file_put_contents($path, $content) === false) {
            throw new QrCodeException("Failed to save QR code to file: {$path}");
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->svg();
    }

    protected static function resolveEcc(string $level): int
    {
        return match (strtoupper($level)) {
            'L' => QrMatrix::ECC_L,
            'Q' => QrMatrix::ECC_Q,
            'H' => QrMatrix::ECC_H,
            default => QrMatrix::ECC_M,
        };
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} [r, g, b, alpha]
     */
    protected static function hexToRgb(string $hex): array
    {
        if ($hex === 'transparent') {
            return [0, 0, 0, 127];
        }

        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return [(int) $r, (int) $g, (int) $b, 0];
    }
}
