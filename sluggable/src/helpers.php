<?php

declare(strict_types=1);

use Bongo\Packages\Sluggable\Slugger;

if (!function_exists('str_slug')) {
    /**
     * Generate a URL friendly "slug" from a given string.
     */
    function str_slug(string $title, string $separator = '-', int $maxLength = 0): string
    {
        return Slugger::slugify($title, $separator, $maxLength);
    }
}
