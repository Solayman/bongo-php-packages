<?php

declare(strict_types=1);

namespace Bongo\Packages\BanglaTools;

class BanglaText
{
    /**
     * Clean and normalize Bengali Unicode text.
     * Removes invisible zero-width characters, fixes redundant diacritics and quotes.
     */
    public static function normalize(string $text): string
    {
        // Remove zero-width spaces (\u200B)
        $text = preg_replace('/[\x{200B}]/u', '', $text);

        // Normalize spaces
        $text = preg_replace('/[ \t]+/u', ' ', $text);

        // Replace English punctuation variations
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim((string) $text);
    }

    /**
     * Generate a URL-friendly slug from Bengali or mixed text.
     * e.g., "আমার সোনার বাংলা - ১ম পর্ব" -> "আমার-সোনার-বাংলা-১ম-পর্ব"
     */
    public static function slug(string $title, string $separator = '-'): string
    {
        // Transliterate / clean whitespace
        $title = self::normalize($title);

        // Replace non-alphanumeric unicode (except Bangla range \x{0980}-\x{09FF} and English letters/digits) with separator
        $slug = preg_replace('/[^\p{L}\p{Nd}\x{0980}-\x{09FF}]+/u', $separator, $title);

        // Remove multiple consecutive separators
        $slug = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, (string) $slug);

        return trim((string) $slug, $separator);
    }
}
