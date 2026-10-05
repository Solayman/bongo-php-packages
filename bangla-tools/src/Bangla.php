<?php

declare(strict_types=1);

namespace Bongo\Packages\BanglaTools;

use DateTimeInterface;

class Bangla
{
    /**
     * Convert English digits to Bengali digits.
     */
    public static function number(int|float|string $number): string
    {
        return BanglaNumber::toBangla($number);
    }

    /**
     * Convert Bengali digits to English digits.
     */
    public static function enNumber(string $number): string
    {
        return BanglaNumber::toEnglish($number);
    }

    /**
     * Convert number to Bengali words (with optional Taka suffix).
     */
    public static function words(int|float $number, bool $asCurrency = false): string
    {
        return BanglaNumber::toWords($number, $asCurrency);
    }

    /**
     * Format currency in Bangladeshi notation (e.g. ৳ ১,৫০,০০০.০০).
     */
    public static function taka(float|int|string $amount, bool $inBanglaDigits = true, string $symbol = '৳'): string
    {
        return BanglaNumber::formatCurrency($amount, $inBanglaDigits, $symbol);
    }

    /**
     * Convert date to Bengali calendar string.
     */
    public static function date(DateTimeInterface|string|null $date = null): string
    {
        return BanglaDate::format($date);
    }

    /**
     * Get structured Bengali calendar details (day, month, season, weekday).
     */
    public static function calendar(DateTimeInterface|string|null $date = null): array
    {
        return BanglaDate::convert($date);
    }

    /**
     * Generate SEO URL slug retaining Bengali characters.
     */
    public static function slug(string $title, string $separator = '-'): string
    {
        return BanglaText::slug($title, $separator);
    }

    /**
     * Normalize Bengali Unicode text.
     */
    public static function normalize(string $text): string
    {
        return BanglaText::normalize($text);
    }
}
