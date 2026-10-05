<?php

declare(strict_types=1);

namespace Bongo\Packages\BanglaTools;

class BanglaNumber
{
    protected static array $enToBnDigits = [
        '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
        '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
    ];

    protected static array $bnToEnDigits = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    protected static array $words = [
        0 => 'শূন্য',
        1 => 'এক', 2 => 'দুই', 3 => 'তিন', 4 => 'চার', 5 => 'পাঁচ',
        6 => 'ছয়', 7 => 'সাত', 8 => 'আট', 9 => 'নয়', 10 => 'দশ',
        11 => 'এগারো', 12 => 'বারো', 13 => 'তেরো', 14 => 'চৌদ্দ', 15 => 'পনেরো',
        16 => 'ষোলো', 17 => 'সতেরো', 18 => 'আঠারো', 19 => 'উনিশ', 20 => 'বিশ',
        21 => 'একুশ', 22 => 'বাইশ', 23 => 'তেইশ', 24 => 'চব্বিশ', 25 => 'পঁচিশ',
        26 => 'ছাব্বিশ', 27 => 'সাতাশ', 28 => 'আঠাশ', 29 => 'উনত্রিশ', 30 => 'ত্রিশ',
        31 => 'একত্রিশ', 32 => 'বত্রিশ', 33 => 'তেত্রিশ', 34 => 'চৌত্রিশ', 35 => 'পঁয়ত্রিশ',
        36 => 'ছত্রিশ', 37 => 'সাঁইত্রিশ', 38 => 'আটত্রিশ', 39 => 'উনচল্লিশ', 40 => 'চল্লিশ',
        41 => 'একচল্লিশ', 42 => 'বিয়াল্লিশ', 43 => 'তেতাল্লিশ', 44 => 'চুয়াল্লিশ', 45 => 'পঁয়তাল্লিশ',
        46 => 'ছেচল্লিশ', 47 => 'সাতচল্লিশ', 48 => 'আটচল্লিশ', 49 => 'উনপঞ্চাশ', 50 => 'পঞ্চাশ',
        51 => 'একান্ন', 52 => 'বায়ান্ন', 53 => 'তিপ্পান্ন', 54 => 'চুয়ান্ন', 55 => 'পঞ্চান্ন',
        56 => 'ছাপ্পান্ন', 57 => 'সাতান্ন', 58 => 'আটান্ন', 59 => 'উনষাট', 60 => 'ষাট',
        61 => 'একষট্টি', 62 => 'বাষট্টি', 63 => 'তেষট্টি', 64 => 'চৌষট্টি', 65 => 'পঁয়ষট্টি',
        66 => 'ছেষট্টি', 67 => 'সাতষট্টি', 68 => 'আটষট্টি', 69 => 'উনসত্তর', 70 => 'সত্তর',
        71 => 'একাত্তর', 72 => 'বাহাত্তর', 73 => 'তিয়াত্তর', 74 => 'চুয়াত্তর', 75 => 'পঁচাত্তর',
        76 => 'ছিয়াত্তর', 77 => 'সাতাত্তর', 78 => 'আটাত্তর', 79 => 'উনাশি', 80 => 'আশি',
        81 => 'একাশি', 82 => 'বিরাশি', 83 => 'তিরাশি', 84 => 'চুরাশি', 85 => 'পঁচাশি',
        86 => 'ছিয়াশি', 87 => 'সাতাশি', 88 => 'আটাশি', 89 => 'ঊননব্বই', 90 => 'নব্বই',
        91 => 'একানব্বই', 92 => 'বিরানব্বই', 93 => 'তিরানব্বই', 94 => 'চুরানব্বই', 95 => 'পঁচানব্বই',
        96 => 'ছিয়ানব্বই', 97 => 'সাতানব্বই', 98 => 'আটানব্বই', 99 => 'নিরানব্বই',
    ];

    /**
     * Convert English digits to Bengali digits.
     */
    public static function toBangla(int|float|string $number): string
    {
        return strtr((string) $number, self::$enToBnDigits);
    }

    /**
     * Convert Bengali digits to English digits.
     */
    public static function toEnglish(string $number): string
    {
        return strtr($number, self::$bnToEnDigits);
    }

    /**
     * Format a number or amount into Bangladeshi comma-separated currency notation.
     * e.g., 1500000.50 -> ৳ ১৫,০০,০০০.৫০
     */
    public static function formatCurrency(float|int|string $amount, bool $inBanglaDigits = true, string $symbol = '৳'): string
    {
        $amountStr = (string) $amount;
        $amountStr = self::toEnglish($amountStr);
        $parts = explode('.', $amountStr);
        $integerPart = preg_replace('/[^\d]/', '', $parts[0]);
        $decimalPart = isset($parts[1]) ? substr($parts[1], 0, 2) : '';

        if ($integerPart === '' || $integerPart === false) {
            $integerPart = '0';
        }

        // South Asian numbering system: last 3 digits, then groups of 2 digits
        $lastThree = substr($integerPart, -3);
        $remaining = substr($integerPart, 0, -3);

        if ($remaining !== '' && $remaining !== false) {
            $formatted = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining) . ',' . $lastThree;
        } else {
            $formatted = $lastThree;
        }

        if ($decimalPart !== '') {
            $formatted .= '.' . str_pad($decimalPart, 2, '0');
        }

        if ($inBanglaDigits) {
            $formatted = self::toBangla($formatted);
        }

        return trim($symbol . ' ' . $formatted);
    }

    /**
     * Convert number to Bengali words (e.g. 150 -> একশত পঞ্চাশ).
     */
    public static function toWords(int|float $number, bool $asCurrency = false): string
    {
        if ($number == 0) {
            return $asCurrency ? 'শূন্য টাকা মাত্র' : 'শূন্য';
        }

        $integer = (int) floor($number);
        $fraction = (int) round(($number - $integer) * 100);

        $words = self::convertIntegerToWords($integer);

        if ($asCurrency) {
            $result = $words . ' টাকা';
            if ($fraction > 0) {
                $fractionWords = self::convertIntegerToWords($fraction);
                $result .= ' ' . $fractionWords . ' পয়সা';
            }
            $result .= ' মাত্র';
            return trim($result);
        }

        return $words;
    }

    protected static function convertIntegerToWords(int $number): string
    {
        if ($number <= 0) {
            return self::$words[0];
        }

        $parts = [];

        // কোটি (Crore - 10,000,000)
        if ($number >= 10000000) {
            $crore = (int) ($number / 10000000);
            $parts[] = self::convertIntegerToWords($crore) . ' কোটি';
            $number %= 10000000;
        }

        // লাখ (Lakh - 100,000)
        if ($number >= 100000) {
            $lakh = (int) ($number / 100000);
            $parts[] = self::$words[$lakh] . ' লাখ';
            $number %= 100000;
        }

        // হাজার (Thousand - 1,000)
        if ($number >= 1000) {
            $thousand = (int) ($number / 1000);
            $parts[] = self::$words[$thousand] . ' হাজার';
            $number %= 1000;
        }

        // শত (Hundred - 100)
        if ($number >= 100) {
            $hundred = (int) ($number / 100);
            $parts[] = self::$words[$hundred] . 'শত';
            $number %= 100;
        }

        // 1 to 99
        if ($number > 0) {
            $parts[] = self::$words[$number];
        }

        return implode(' ', $parts);
    }
}
