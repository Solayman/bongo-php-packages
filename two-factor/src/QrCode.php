<?php

declare(strict_types=1);

namespace Bongo\Packages\TwoFactor;

class QrCode
{
    // Versions 1 to 6 table for Error Correction Level L
    // [version => [size, total_codewords, data_codewords, ec_codewords, blocks, alignment_coords]]
    protected static array $versions = [
        1 => ['size' => 21, 'total' => 26,  'data' => 19,  'ec' => 7,  'blocks' => 1, 'align' => []],
        2 => ['size' => 25, 'total' => 44,  'data' => 34,  'ec' => 10, 'blocks' => 1, 'align' => [6, 18]],
        3 => ['size' => 29, 'total' => 70,  'data' => 55,  'ec' => 15, 'blocks' => 1, 'align' => [6, 22]],
        4 => ['size' => 33, 'total' => 100, 'data' => 80,  'ec' => 20, 'blocks' => 1, 'align' => [6, 26]],
        5 => ['size' => 37, 'total' => 134, 'data' => 108, 'ec' => 26, 'blocks' => 1, 'align' => [6, 30]],
        6 => ['size' => 41, 'total' => 172, 'data' => 136, 'ec' => 18, 'blocks' => 2, 'align' => [6, 34]],
    ];

    protected static array $exp = [];
    protected static array $log = [];

    protected static function initGf(): void
    {
        if (!empty(self::$exp)) {
            return;
        }
        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    protected static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }
        return self::$exp[self::$log[$x] + self::$log[$y]];
    }

    protected static function rsGeneratorPoly(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = [1, self::$exp[$i]];
            $res = array_fill(0, count($poly) + 1, 0);
            for ($j = 0; $j < count($poly); $j++) {
                for ($k = 0; $k < count($next); $k++) {
                    $res[$j + $k] ^= self::gfMul($poly[$j], $next[$k]);
                }
            }
            $poly = $res;
        }
        return $poly;
    }

    protected static function rsEncode(array $data, int $ecCount): array
    {
        self::initGf();
        $gen = self::rsGeneratorPoly($ecCount);
        $remainder = array_fill(0, $ecCount, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;
            if ($factor !== 0) {
                for ($i = 0; $i < $ecCount; $i++) {
                    $remainder[$i] ^= self::gfMul($gen[$i + 1], $factor);
                }
            }
        }
        return $remainder;
    }

    /**
     * Generate pure SVG string for a given text.
     */
    public static function svg(string $text, int $size = 200, string $fg = '#000000', string $bg = '#ffffff'): string
    {
        $matrix = self::encode($text);
        $n = count($matrix);
        $margin = 4;
        $totalSize = $n + ($margin * 2);

        $rects = [];
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrix[$y][$x]) {
                    $rects[] = sprintf('<rect x="%d" y="%d" width="1" height="1"/>', $x + $margin, $y + $margin);
                }
            }
        }

        return sprintf(
            '<svg viewBox="0 0 %d %d" width="%d" height="%d" xmlns="http://www.w3.org/2000/svg">' .
            '<rect width="100%%" height="100%%" fill="%s"/>' .
            '<g fill="%s">%s</g>' .
            '</svg>',
            $totalSize,
            $totalSize,
            $size,
            $size,
            htmlspecialchars($bg, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($fg, ENT_QUOTES, 'UTF-8'),
            implode('', $rects)
        );
    }

    /**
     * Generate base64 data URI for an inline <img> tag.
     */
    public static function toDataUri(string $text, int $size = 200): string
    {
        $svg = self::svg($text, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Encode text into a 2D boolean matrix (true = dark, false = light).
     */
    public static function encode(string $text): array
    {
        self::initGf();
        $len = strlen($text);
        $ver = null;
        foreach (self::$versions as $v => $info) {
            $maxDataBytes = $info['data'];
            if ($len <= ($maxDataBytes - 2)) {
                $ver = $v;
                break;
            }
        }
        if ($ver === null) {
            $ver = 6;
            if ($len > 130) {
                $text = substr($text, 0, 130);
                $len = 130;
            }
        }

        $info = self::$versions[$ver];
        $dataCodewordsCount = $info['data'];
        $ecCodewordsCount = $info['ec'];

        // Bit stream construction: Byte mode (0100) + 8-bit length
        $bits = '0100' . sprintf('%08b', $len);
        for ($i = 0; $i < $len; $i++) {
            $bits .= sprintf('%08b', ord($text[$i]));
        }

        // Terminator up to 4 zeros
        $bits .= substr('0000', 0, min(4, ($dataCodewordsCount * 8) - strlen($bits)));

        // Pad to byte boundary
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }

        // Convert to data codewords
        $dataCodewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataCodewords[] = bindec(substr($bits, $i, 8));
        }

        // Pad with alternating 0xEC and 0x11
        $pad = [0xEC, 0x11];
        $padIdx = 0;
        while (count($dataCodewords) < $dataCodewordsCount) {
            $dataCodewords[] = $pad[$padIdx % 2];
            $padIdx++;
        }

        // Compute error correction codewords
        $blocks = $info['blocks'];
        $allCodewords = [];
        if ($blocks === 1) {
            $ec = self::rsEncode($dataCodewords, $ecCodewordsCount);
            $allCodewords = array_merge($dataCodewords, $ec);
        } else {
            $halfData = (int) ($dataCodewordsCount / $blocks);
            $b1Data = array_slice($dataCodewords, 0, $halfData);
            $b2Data = array_slice($dataCodewords, $halfData);
            $b1Ec = self::rsEncode($b1Data, $ecCodewordsCount);
            $b2Ec = self::rsEncode($b2Data, $ecCodewordsCount);

            // Interleave data
            for ($i = 0; $i < $halfData; $i++) {
                $allCodewords[] = $b1Data[$i];
                $allCodewords[] = $b2Data[$i];
            }
            // Interleave EC
            for ($i = 0; $i < $ecCodewordsCount; $i++) {
                $allCodewords[] = $b1Ec[$i];
                $allCodewords[] = $b2Ec[$i];
            }
        }

        // Build matrix
        $n = $info['size'];
        $matrix = array_fill(0, $n, array_fill(0, $n, null));

        // 1. Finder patterns
        self::placeFinder($matrix, 0, 0);
        self::placeFinder($matrix, $n - 7, 0);
        self::placeFinder($matrix, 0, $n - 7);

        // 2. Alignment patterns
        $align = $info['align'];
        if (!empty($align)) {
            foreach ($align as $x) {
                foreach ($align as $y) {
                    if ($matrix[$y][$x] !== null) {
                        continue;
                    }
                    self::placeAlignment($matrix, $x, $y);
                }
            }
        }

        // 3. Timing patterns
        for ($i = 8; $i < $n - 8; $i++) {
            $val = ($i % 2 === 0);
            if ($matrix[6][$i] === null) {
                $matrix[6][$i] = $val;
            }
            if ($matrix[$i][6] === null) {
                $matrix[$i][6] = $val;
            }
        }

        // 4. Dark module
        $matrix[4 * $ver + 9][8] = true;

        // Reserve format info areas
        for ($i = 0; $i < 9; $i++) {
            if ($matrix[8][$i] === null) {
                $matrix[8][$i] = false;
            }
            if ($matrix[$i][8] === null) {
                $matrix[$i][8] = false;
            }
        }
        for ($i = $n - 8; $i < $n; $i++) {
            if ($matrix[8][$i] === null) {
                $matrix[8][$i] = false;
            }
            if ($matrix[$i][8] === null) {
                $matrix[$i][8] = false;
            }
        }

        // Convert codewords to bits
        $codewordBits = '';
        foreach ($allCodewords as $cw) {
            $codewordBits .= sprintf('%08b', $cw);
        }

        // 5. Place data bits in matrix (zig-zag right-to-left, bottom-to-top/top-to-bottom)
        $bitIdx = 0;
        $totalBits = strlen($codewordBits);
        $up = true;

        for ($right = $n - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right--; // Skip vertical timing line
            }
            $cols = [$right, $right - 1];
            $rows = $up ? range($n - 1, 0) : range(0, $n - 1);

            foreach ($rows as $y) {
                foreach ($cols as $x) {
                    if ($matrix[$y][$x] === null) {
                        $bit = ($bitIdx < $totalBits) ? ($codewordBits[$bitIdx] === '1') : false;
                        $bitIdx++;

                        // Mask Pattern 0: (row + col) % 2 === 0
                        $mask = (($y + $x) % 2 === 0);
                        $matrix[$y][$x] = $bit ^ $mask;
                    }
                }
            }
            $up = !$up;
        }

        // 6. Format info for EC Level L (01) and Mask Pattern 0 (000)
        $fmtBits = '010001111010110';
        self::placeFormatInfo($matrix, $fmtBits);

        return $matrix;
    }

    protected static function placeFinder(array &$matrix, int $x, int $y): void
    {
        $n = count($matrix);
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rx = $x + $c;
                $ry = $y + $r;
                if ($rx >= 0 && $rx < $n && $ry >= 0 && $ry < $n) {
                    if ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) {
                        $isDark = ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                        $matrix[$ry][$rx] = $isDark;
                    } else {
                        $matrix[$ry][$rx] = false; // Separator
                    }
                }
            }
        }
    }

    protected static function placeAlignment(array &$matrix, int $cx, int $cy): void
    {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $matrix[$cy + $r][$cx + $c] = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
            }
        }
    }

    protected static function placeFormatInfo(array &$matrix, string $bits): void
    {
        $n = count($matrix);
        $coords1 = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5],
            [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        $coords2 = [
            [$n - 1, 8], [$n - 2, 8], [$n - 3, 8], [$n - 4, 8], [$n - 5, 8], [$n - 6, 8], [$n - 7, 8],
            [8, $n - 8], [8, $n - 7], [8, $n - 6], [8, $n - 5], [8, $n - 4], [8, $n - 3], [8, $n - 2], [8, $n - 1],
        ];

        for ($i = 0; $i < 15; $i++) {
            $val = ($bits[$i] === '1');
            [$y1, $x1] = $coords1[$i];
            $matrix[$y1][$x1] = $val;

            [$y2, $x2] = $coords2[$i];
            $matrix[$y2][$x2] = $val;
        }
    }
}
