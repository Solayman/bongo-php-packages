<?php

declare(strict_types=1);

use Bongo\Packages\Taggable\Models\Tag;

if (!function_exists('tag')) {
    /**
     * Find or create a Tag, or retrieve Tag model instance.
     */
    function tag(?string $name = null, ?string $type = null): Tag
    {
        if ($name !== null) {
            return Tag::findOrCreate($name, $type);
        }
        return new Tag();
    }
}

if (!function_exists('popular_tags')) {
    /**
     * Retrieve the most popular tags.
     *
     * @return Tag[]
     */
    function popular_tags(int $limit = 10, ?string $type = null): array
    {
        return Tag::popular($limit, $type);
    }
}

if (!function_exists('tag_cloud')) {
    /**
     * Retrieve weighted tag cloud array.
     */
    function tag_cloud(?string $type = null, int $minSize = 12, int $maxSize = 28): array
    {
        return Tag::tagCloud($type, $minSize, $maxSize);
    }
}
