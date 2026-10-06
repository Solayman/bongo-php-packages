<?php

declare(strict_types=1);

namespace Bongo\Packages\Sluggable;

class Slugger
{
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
        // 1. Remove HTML tags and entities
        $string = strip_tags(html_entity_decode($string, ENT_QUOTES, 'UTF-8'));

        // 2. Replace common symbols
        $string = str_replace(['&', '@'], [' and ', ' at '], $string);

        // 3. Transliterate Latin accents if possible without mangling non-Latin scripts (Bengali, Arabic, etc.)
        if (function_exists('transliterator_transliterate')) {
            $transliterated = @transliterator_transliterate('Latin-ASCII', $string);
            if ($transliterated !== false && $transliterated !== '') {
                $string = $transliterated;
            }
        }

        // 4. Lowercase safely with multibyte support
        $string = mb_strtolower(trim($string), 'UTF-8');

        // 4. Replace whitespace, underscores, and dashes with standard space
        $string = preg_replace('/[\s_\-]+/u', ' ', $string);

        // 5. Replace any character that is NOT a unicode letter, mark, or number with empty/space
        // \p{L} = letters, \p{M} = combining marks (Bengali vowels, ligatures), \p{N} = numbers
        $string = preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', '', (string) $string);

        // 6. Replace multiple spaces with the designated separator
        $sep = preg_quote($separator, '/');
        $string = preg_replace('/\s+/u', $separator, trim((string) $string));

        // 7. Collapse duplicate separators
        $string = preg_replace('/' . $sep . '+/u', $separator, (string) $string);

        // 8. Trim separator from start and end
        $string = trim((string) $string, $separator);

        // 9. Handle max length truncation cleanly without breaking words
        if ($maxLength > 0 && mb_strlen($string, 'UTF-8') > $maxLength) {
            $truncated = mb_substr($string, 0, $maxLength, 'UTF-8');
            $lastSep = mb_strrpos($truncated, $separator, 0, 'UTF-8');
            if ($lastSep !== false && $lastSep > ($maxLength * 0.5)) {
                $string = mb_substr($truncated, 0, $lastSep, 'UTF-8');
            } else {
                $string = $truncated;
            }
            $string = trim($string, $separator);
        }

        return $string !== '' ? $string : 'n-a';
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

        $isReserved = in_array(strtolower($slug), array_map('strtolower', $reserved), true);

        while ($isReserved || $existsChecker($slug)) {
            $slug = "{$baseSlug}{$separator}{$counter}";
            $counter++;
            $isReserved = in_array(strtolower($slug), array_map('strtolower', $reserved), true);
        }

        return $slug;
    }
}
