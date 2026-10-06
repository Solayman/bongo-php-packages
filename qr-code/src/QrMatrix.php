<?php

declare(strict_types=1);

namespace Bongo\Packages\QrCode;

use Bongo\Packages\QrCode\Exceptions\QrCodeException;

/**
 * Pure PHP QR Code 2D Matrix Generator (Versions 1-10, Byte Mode, ECC L/M/Q/H).
 */
class QrMatrix
{
    public const ECC_L = 0; // 7% recovery
    public const ECC_M = 1; // 15% recovery
    public const ECC_Q = 2; // 25% recovery
    public const ECC_H = 3; // 30% recovery

    // Capacity table [version][ecc] = [totalDataBytes, ecBytesPerBlock, numBlocks]
    protected static array $capacities = [
        1 => [self::ECC_L => [19, 7, 1], self::ECC_M => [16, 10, 1], self::ECC_Q => [13, 13, 1], self::ECC_H => [9, 17, 1]],
        2 => [self::ECC_L => [34, 10, 1], self::ECC_M => [28, 16, 1], self::ECC_Q => [22, 22, 1], self::ECC_H => [16, 28, 1]],
        3 => [self::ECC_L => [55, 15, 1], self::ECC_M => [44, 26, 1], self::ECC_Q => [34, 18, 2], self::ECC_H => [26, 22, 2]],
        4 => [self::ECC_L => [80, 20, 1], self::ECC_M => [64, 18, 2], self::ECC_Q => [48, 26, 2], self::ECC_H => [36, 16, 4]],
        5 => [self::ECC_L => [108, 26, 1], self::ECC_M => [86, 24, 2], self::ECC_Q => [62, 18, 4], self::ECC_H => [46, 22, 4]],
        6 => [self::ECC_L => [136, 18, 2], self::ECC_M => [108, 16, 4], self::ECC_Q => [76, 24, 4], self::ECC_H => [60, 28, 4]],
        7 => [self::ECC_L => [156, 20, 2], self::ECC_M => [124, 18, 4], self::ECC_Q => [88, 18, 6], self::ECC_H => [66, 26, 5]],
        8 => [self::ECC_L => [194, 24, 2], self::ECC_M => [154, 22, 4], self::ECC_Q => [110, 22, 6], self::ECC_H => [86, 26, 6]],
        9 => [self::ECC_L => [232, 30, 2], self::ECC_M => [182, 22, 5], self::ECC_Q => [132, 20, 8], self::ECC_H => [100, 24, 8]],
        10 => [self::ECC_L => [274, 18, 4], self::ECC_M => [216, 26, 5], self::ECC_Q => [154, 24, 8], self::ECC_H => [122, 28, 8]],
    ];

    protected static array $alignmentPatternPositions = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /** @var int[] */
    protected static array $expTable = [];
    /** @var int[] */
    protected static array $logTable = [];

    protected static bool $gfInitialized = false;

    /**
     * Generate the boolean matrix for the given text.
     * Returns 2D array of booleans (true = dark module, false = light module).
     *
     * @return array<int, array<int, bool>>
     */
    public static function create(string $text, int $eccLevel = self::ECC_M): array
    {
        static::initGf();

        $bytes = array_values(unpack('C*', $text));
        $dataLen = count($bytes);

        // Determine minimum QR version needed
        $version = null;
        for ($v = 1; $v <= 10; $v++) {
            if (isset(static::$capacities[$v][$eccLevel])) {
                $maxBytes = static::$capacities[$v][$eccLevel][0];
                if ($dataLen + 2 <= $maxBytes) { // byte mode header takes 12 bits ~ 2 bytes
                    $version = $v;
                    break;
                }
            }
        }

        if ($version === null) {
            throw new QrCodeException("Text exceeds maximum capacity of Version 10 QR code (" . strlen($text) . " bytes).");
        }

        $matrixSize = 21 + 4 * ($version - 1);
        $capacity = static::$capacities[$version][$eccLevel];
        $totalDataBytes = $capacity[0];
        $ecBytesPerBlock = $capacity[1];
        $numBlocks = $capacity[2];

        // 1. Build bit stream (Byte Mode: 0100 + char count + data)
        $bits = [];
        static::appendBits($bits, 0b0100, 4); // Byte mode indicator
        static::appendBits($bits, $dataLen, 8); // 8-bit character count for v1-9
        foreach ($bytes as $b) {
            static::appendBits($bits, $b, 8);
        }

        // Terminator: up to 4 zero bits
        $neededBits = $totalDataBytes * 8;
        $terminatorBits = min(4, $neededBits - count($bits));
        static::appendBits($bits, 0, $terminatorBits);

        // Pad to 8-bit boundary
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        // Pad bytes (0xEC, 0x11 alternate)
        $padBytes = [0xEC, 0x11];
        $p = 0;
        while (count($bits) < $neededBits) {
            static::appendBits($bits, $padBytes[$p % 2], 8);
            $p++;
        }

        // Convert bits into byte codewords
        $dataCodewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($b = 0; $b < 8; $b++) {
                $byte = ($byte << 1) | $bits[$i + $b];
            }
            $dataCodewords[] = $byte;
        }

        // 2. Generate Reed-Solomon Error Correction Codewords per block
        $blockSize = (int) floor($totalDataBytes / $numBlocks);
        $extraBlocks = $totalDataBytes % $numBlocks;

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;

        for ($b = 0; $b < $numBlocks; $b++) {
            $currentBlockSize = $blockSize + ($b >= ($numBlocks - $extraBlocks) ? 1 : 0);
            $blockData = array_slice($dataCodewords, $offset, $currentBlockSize);
            $offset += $currentBlockSize;
            $dataBlocks[] = $blockData;
            $ecBlocks[] = static::calculateErrorCorrection($blockData, $ecBytesPerBlock);
        }

        // Interleave data codewords
        $finalCodewords = [];
        $maxDataLen = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $finalCodewords[] = $block[$i];
                }
            }
        }

        // Interleave EC codewords
        for ($i = 0; $i < $ecBytesPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                if (isset($block[$i])) {
                    $finalCodewords[] = $block[$i];
                }
            }
        }

        // 3. Initialize matrix and reserved flags
        $matrix = array_fill(0, $matrixSize, array_fill(0, $matrixSize, null));
        $reserved = array_fill(0, $matrixSize, array_fill(0, $matrixSize, false));

        // Place function patterns
        static::placeFinderPatterns($matrix, $reserved, $matrixSize);
        static::placeTimingPatterns($matrix, $reserved, $matrixSize);
        static::placeAlignmentPatterns($matrix, $reserved, $version);
        static::reserveFormatArea($reserved, $matrixSize);

        // Dark module
        $matrix[4 * $version + 9][8] = true;
        $reserved[4 * $version + 9][8] = true;

        // 4. Place Data Codewords (Zig-Zag)
        $finalBits = [];
        foreach ($finalCodewords as $cw) {
            static::appendBits($finalBits, $cw, 8);
        }
        // Remainder bits for versions that require them
        $remainderBits = match ($version) {
            2, 3, 4, 5, 6 => 7,
            default => 0,
        };
        for ($r = 0; $r < $remainderBits; $r++) {
            $finalBits[] = 0;
        }

        $bitIdx = 0;
        $bitCount = count($finalBits);
        $col = $matrixSize - 1;
        $upwards = true;

        while ($col > 0) {
            if ($col === 6) {
                $col--; // Skip vertical timing line
            }

            for ($step = 0; $step < $matrixSize; $step++) {
                $row = $upwards ? ($matrixSize - 1 - $step) : $step;
                for ($c = 0; $c < 2; $c++) {
                    $currCol = $col - $c;
                    if (!$reserved[$row][$currCol]) {
                        $matrix[$row][$currCol] = $bitIdx < $bitCount ? ($finalBits[$bitIdx] === 1) : false;
                        $bitIdx++;
                    }
                }
            }

            $upwards = !$upwards;
            $col -= 2;
        }

        // 5. Evaluate Best Mask (0..7)
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $maskedMatrix = $matrix;
            for ($r = 0; $r < $matrixSize; $r++) {
                for ($c = 0; $c < $matrixSize; $c++) {
                    if (!$reserved[$r][$c]) {
                        if (static::isMaskCondition($mask, $r, $c)) {
                            $maskedMatrix[$r][$c] = !$maskedMatrix[$r][$c];
                        }
                    }
                }
            }

            static::placeFormatInfo($maskedMatrix, $eccLevel, $mask, $matrixSize);
            $penalty = static::calculatePenalty($maskedMatrix, $matrixSize);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $maskedMatrix;
            }
        }

        // Convert any leftover nulls to false
        for ($r = 0; $r < $matrixSize; $r++) {
            for ($c = 0; $c < $matrixSize; $c++) {
                $bestMatrix[$r][$c] = (bool) $bestMatrix[$r][$c];
            }
        }

        return $bestMatrix;
    }

    protected static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    protected static function initGf(): void
    {
        if (static::$gfInitialized) {
            return;
        }

        static::$expTable = array_fill(0, 512, 0);
        static::$logTable = array_fill(0, 256, 0);

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            static::$expTable[$i] = $x;
            static::$logTable[$x] = $i;
            $x <<= 1;
            if ($x >= 256) {
                $x ^= 0x11D; // Primitive polynomial 285
            }
        }

        for ($i = 255; $i < 512; $i++) {
            static::$expTable[$i] = static::$expTable[$i - 255];
        }

        static::$gfInitialized = true;
    }

    protected static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }
        return static::$expTable[static::$logTable[$x] + static::$logTable[$y]];
    }

    protected static function calculateErrorCorrection(array $data, int $ecCount): array
    {
        // Generator polynomial for ecCount
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            $factor = static::$expTable[$i];
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j] ^= $gen[$j];
                $next[$j + 1] ^= static::gfMul($gen[$j], $factor);
            }
            $gen = $next;
        }

        $res = array_merge($data, array_fill(0, $ecCount, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $res[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $res[$i + $j] ^= static::gfMul($gen[$j], $coef);
                }
            }
        }

        return array_slice($res, count($data));
    }

    protected static function placeFinderPatterns(array &$matrix, array &$reserved, int $size): void
    {
        $corners = [
            [0, 0],
            [$size - 7, 0],
            [0, $size - 7],
        ];

        foreach ($corners as [$r0, $c0]) {
            for ($r = 0; $r < 7; $r++) {
                for ($c = 0; $c < 7; $c++) {
                    $isDark = ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                    $matrix[$r0 + $r][$c0 + $c] = $isDark;
                    $reserved[$r0 + $r][$c0 + $c] = true;
                }
            }

            // Separator white ring around pattern
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $rr = $r0 + $r;
                    $cc = $c0 + $c;
                    if ($rr >= 0 && $rr < $size && $cc >= 0 && $cc < $size) {
                        if (!$reserved[$rr][$cc]) {
                            $matrix[$rr][$cc] = false;
                            $reserved[$rr][$cc] = true;
                        }
                    }
                }
            }
        }
    }

    protected static function placeTimingPatterns(array &$matrix, array &$reserved, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = ($i % 2 === 0);
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = ($i % 2 === 0);
                $reserved[$i][6] = true;
            }
        }
    }

    protected static function placeAlignmentPatterns(array &$matrix, array &$reserved, int $version): void
    {
        $coords = static::$alignmentPatternPositions[$version] ?? [];
        if (empty($coords)) {
            return;
        }

        foreach ($coords as $rCenter) {
            foreach ($coords as $cCenter) {
                // Do not collide with finder patterns
                if ($reserved[$rCenter][$cCenter]) {
                    continue;
                }

                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $isDark = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
                        $matrix[$rCenter + $r][$cCenter + $c] = $isDark;
                        $reserved[$rCenter + $r][$cCenter + $c] = true;
                    }
                }
            }
        }
    }

    protected static function reserveFormatArea(array &$reserved, int $size): void
    {
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
    }

    protected static function isMaskCondition(int $mask, int $r, int $c): bool
    {
        return match ($mask) {
            0 => (($r + $c) % 2 === 0),
            1 => ($r % 2 === 0),
            2 => ($c % 3 === 0),
            3 => (($r + $c) % 3 === 0),
            4 => (((int) floor($r / 2) + (int) floor($c / 3)) % 2 === 0),
            5 => ((($r * $c) % 2) + (($r * $c) % 3) === 0),
            6 => (((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0),
            7 => (((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0),
            default => false,
        };
    }

    protected static function placeFormatInfo(array &$matrix, int $eccLevel, int $mask, int $size): void
    {
        // 5-bit format: (eccIndicator << 3) | mask
        $eccBits = match ($eccLevel) {
            self::ECC_L => 0b01,
            self::ECC_M => 0b00,
            self::ECC_Q => 0b11,
            self::ECC_H => 0b10,
        };

        $formatData = ($eccBits << 3) | $mask;
        $rem = $formatData << 10;
        $poly = 0x537; // 10100110111
        for ($i = 4; $i >= 0; $i--) {
            if (($rem & (1 << ($i + 10))) !== 0) {
                $rem ^= ($poly << $i);
            }
        }

        $formatWord = (($formatData << 10) | $rem) ^ 0x5412; // XOR with mask 101010000010010

        // Place 15 bits
        $pos = [
            [0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8],
            [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0],
        ];

        for ($i = 0; $i < 15; $i++) {
            $bit = (($formatWord >> $i) & 1) === 1;
            [$r, $c] = $pos[$i];
            $matrix[$r][$c] = $bit;
        }

        // Duplicate on opposite sides
        for ($i = 0; $i < 7; $i++) {
            $bit = (($formatWord >> $i) & 1) === 1;
            $matrix[8][$size - 1 - $i] = $bit;
        }
        for ($i = 7; $i < 15; $i++) {
            $bit = (($formatWord >> $i) & 1) === 1;
            $matrix[$size - 15 + $i][8] = $bit;
        }
    }

    protected static function calculatePenalty(array $matrix, int $size): int
    {
        $penalty = 0;

        // Rule 1: 5+ consecutive same color modules
        for ($r = 0; $r < $size; $r++) {
            $count = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($matrix[$r][$c] === $matrix[$r][$c - 1]) {
                    $count++;
                    if ($count === 5) {
                        $penalty += 3;
                    } elseif ($count > 5) {
                        $penalty++;
                    }
                } else {
                    $count = 1;
                }
            }
        }

        for ($c = 0; $c < $size; $c++) {
            $count = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($matrix[$r][$c] === $matrix[$r - 1][$c]) {
                    $count++;
                    if ($count === 5) {
                        $penalty += 3;
                    } elseif ($count > 5) {
                        $penalty++;
                    }
                } else {
                    $count = 1;
                }
            }
        }

        // Rule 2: 2x2 blocks
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $val = $matrix[$r][$c];
                if ($matrix[$r + 1][$c] === $val && $matrix[$r][$c + 1] === $val && $matrix[$r + 1][$c + 1] === $val) {
                    $penalty += 3;
                }
            }
        }

        return $penalty;
    }
}
