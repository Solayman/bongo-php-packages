<?php

declare(strict_types=1);

namespace Bongo\Packages\Sluggable;

class Slugger
{
    /** @var array<string, string> */
    protected static array $slugCache = [];

    /**
     * Clear memoized slug cache.
     */
    public static function flushCache(): void
    {
        self::$slugCache = [];
    }

    /**
     * Generate a URL-friendly slug from any string, with full UTF-8/Bengali support.
     *
     * @param string $string Raw title or text
     * @param string $separator Word separator (default '-')
     * @param int $maxLength Maximum character length (0 = unlimited)
     * @return string
     */
    public static function slugify(string $string, string $separator = '-', int $maxLength = 0): string
    {
        $cacheKey = $string . "\0" . $separator . "\0" . $maxLength;
        if (isset(self::$slugCache[$cacheKey])) {
            return self::$slugCache[$cacheKey];
        }

        // 1. Remove HTML tags and entities
        $clean = strip_tags(html_entity_decode($string, ENT_QUOTES, 'UTF-8'));

        // 2. Replace common symbols
        $clean = str_replace(['&', '@'], [' and ', ' at '], $clean);

        // 3. Transliterate Latin accents if possible without mangling non-Latin scripts (Bengali, Arabic, etc.)
        if (function_exists('transliterator_transliterate')) {
            $transliterated = @transliterator_transliterate('Latin-ASCII', $clean);
            if ($transliterated !== false && $transliterated !== '') {
                $clean = $transliterated;
            }
        }

        // 4. Lowercase safely with multibyte support
        $clean = mb_strtolower(trim($clean), 'UTF-8');

        // 4. Replace whitespace, underscores, and dashes with standard space
        $clean = preg_replace('/[\s_\-]+/u', ' ', $clean);

        // 5. Replace any character that is NOT a unicode letter, mark, or number with empty/space
        // \p{L} = letters, \p{M} = combining marks (Bengali vowels, ligatures), \p{N} = numbers
        $clean = preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', '', (string) $clean);

        // 6. Replace multiple spaces with the designated separator
        $sep = preg_quote($separator, '/');
        $clean = preg_replace('/\s+/u', $separator, trim((string) $clean));

        // 7. Collapse duplicate separators
        $clean = preg_replace('/' . $sep . '+/u', $separator, (string) $clean);

        // 8. Trim separator from start and end
        $clean = trim((string) $clean, $separator);

        // 9. Handle max length truncation cleanly without breaking words
        if ($maxLength > 0 && mb_strlen($clean, 'UTF-8') > $maxLength) {
            $truncated = mb_substr($clean, 0, $maxLength, 'UTF-8');
            $lastSep = mb_strrpos($truncated, $separator, 0, 'UTF-8');
            if ($lastSep !== false && $lastSep > ($maxLength * 0.5)) {
                $clean = mb_substr($truncated, 0, $lastSep, 'UTF-8');
            } else {
                $clean = $truncated;
            }
            $clean = trim($clean, $separator);
        }

        $result = $clean !== '' ? $clean : 'n-a';
        if (count(self::$slugCache) < 1000) {
            self::$slugCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Alias for slugify().
     */
    public static function slug(string $string, string $separator = '-', int $maxLength = 0): string
    {
        return static::slugify($string, $separator, $maxLength);
    }

    /**
     * Generate a unique slug by evaluating collisions via a callback.
     *
     * @param string $baseSlug Initial slug to test
     * @param callable $existsChecker fn(string $slug): bool
     * @param string $separator Separator for suffix (default '-')
     * @param array $reserved List of reserved slugs that cannot be used
     * @return string
     */
    public static function makeUnique(
        string $baseSlug,
        callable $existsChecker,
        string $separator = '-',
        array $reserved = []
    ): string {
        $slug = $baseSlug;
        $counter = 1;

        // Pre-compute O(1) hash map for reserved slugs to eliminate allocations inside the loop
        $reservedLookup = !empty($reserved) ? array_flip(array_map('strtolower', $reserved)) : [];
        $isReserved = isset($reservedLookup[strtolower($slug)]);

        while ($isReserved || $existsChecker($slug)) {
            $slug = "{$baseSlug}{$separator}{$counter}";
            $counter++;
            $isReserved = isset($reservedLookup[strtolower($slug)]);
        }

        return $slug;
    }
}
