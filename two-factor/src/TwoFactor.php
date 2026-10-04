<?php

declare(strict_types=1);

namespace Bongo\Packages\TwoFactor;

use InvalidArgumentException;

class TwoFactor
{
    protected const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new random Base32 secret key (default 16 chars / 80 bits).
     */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        $max = strlen(self::BASE32_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $max)];
        }
        return $secret;
    }

    /**
     * Compute RFC 6238 TOTP code for a given Base32 secret.
     */
    public static function generateTotp(string $secret, ?int $timestamp = null, int $digits = 6, int $period = 30): string
    {
        $timestamp = $timestamp ?? time();
        $secretBinary = self::base32Decode($secret);

        $timeSlice = (int) floor($timestamp / $period);
        $timeBytes = pack('N*', 0, $timeSlice);

        $hash = hash_hmac('sha1', $timeBytes, $secretBinary, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;

        $binary =
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff);

        $otp = $binary % (10 ** $digits);
        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify user submitted TOTP code against secret, tolerating +/- $window intervals.
     */
    public static function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null, int $digits = 6, int $period = 30): bool
    {
        $cleanCode = trim($code);
        if (strlen($cleanCode) !== $digits || !ctype_digit($cleanCode)) {
            return false;
        }

        $timestamp = $timestamp ?? time();
        for ($i = -$window; $i <= $window; $i++) {
            $testTime = $timestamp + ($i * $period);
            $expected = self::generateTotp($secret, $testTime, $digits, $period);
            if (hash_equals($expected, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate standard otpauth URI.
     */
    public static function qrCodeUrl(string $label, string $secret, ?string $issuer = null): string
    {
        $issuer = $issuer ?? (function_exists('config') ? config('two_factor.issuer') : null) ?? env('APP_NAME', 'Bongo');
        $cleanLabel = trim($label);
        $fullLabel = rawurlencode($issuer) . ':' . rawurlencode($cleanLabel);

        $params = [
            'secret'    => strtoupper(str_replace([' ', '-'], '', $secret)),
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => (int) (function_exists('config') ? config('two_factor.digits', 6) : 6),
            'period'    => (int) (function_exists('config') ? config('two_factor.period', 30) : 30),
        ];

        return 'otpauth://totp/' . $fullLabel . '?' . http_build_query($params);
    }

    /**
     * Generate SVG QR code for the given user and secret.
     */
    public static function qrCodeSvg(string $label, string $secret, ?string $issuer = null, int $size = 200): string
    {
        $url = self::qrCodeUrl($label, $secret, $issuer);
        return QrCode::svg($url, $size);
    }

    /**
     * Generate base64 data URI for embedding in <img> tag.
     */
    public static function qrCodeDataUri(string $label, string $secret, ?string $issuer = null, int $size = 200): string
    {
        $url = self::qrCodeUrl($label, $secret, $issuer);
        return QrCode::toDataUri($url, $size);
    }

    /**
     * Generate single-use recovery / backup codes (e.g. 8 codes formatted as ABCD-1234).
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $bytes = bin2hex(random_bytes(4)); // 8 hex characters
            $codes[] = strtoupper(substr($bytes, 0, 4) . '-' . substr($bytes, 4, 4));
        }
        return $codes;
    }

    /**
     * Hash recovery code for secure database storage.
     */
    public static function hashRecoveryCode(string $code): string
    {
        $normalized = strtoupper(str_replace(['-', ' '], '', trim($code)));
        return hash('sha256', $normalized);
    }

    /**
     * Verify and consume a single-use recovery code from the array of hashed codes.
     * Returns true if valid and updates $hashedCodes by removing the matched code.
     */
    public static function verifyRecoveryCode(string $code, array &$hashedCodes): bool
    {
        $inputHash = self::hashRecoveryCode($code);
        foreach ($hashedCodes as $index => $storedHash) {
            if (hash_equals($storedHash, $inputHash)) {
                unset($hashedCodes[$index]);
                $hashedCodes = array_values($hashedCodes);
                return true;
            }
        }
        return false;
    }

    /**
     * Decode Base32 string to binary bytes.
     */
    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(str_replace([' ', '-', '='], '', $b32));
        $len = strlen($b32);
        if ($len === 0) {
            return '';
        }

        $binary = '';
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos(self::BASE32_ALPHABET, $b32[$i]);
            if ($pos === false) {
                throw new InvalidArgumentException("Invalid Base32 character: {$b32[$i]}");
            }
            $binary .= sprintf('%05b', $pos);
        }

        $bytes = '';
        for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
            $bytes .= chr((int) bindec(substr($binary, $i, 8)));
        }

        return $bytes;
    }

    /**
     * Encode binary bytes to Base32 string.
     */
    public static function base32Encode(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        $bitString = '';
        $len = strlen($binary);
        for ($i = 0; $i < $len; $i++) {
            $bitString .= sprintf('%08b', ord($binary[$i]));
        }

        $b32 = '';
        for ($i = 0; $i < strlen($bitString); $i += 5) {
            $chunk = substr($bitString, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $b32 .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $b32;
    }
}
