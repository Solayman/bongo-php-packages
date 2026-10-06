<?php

declare(strict_types=1);

use Bongo\Packages\Reviews\Models\Review;
use Bongo\Packages\Reviews\ReviewManager;

if (!function_exists('reviews')) {
    /**
     * Get review manager or find review by ID.
     */
    function reviews(?int $id = null): Review|ReviewManager|null
    {
        if ($id !== null) {
            return Review::find($id);
        }
        return new ReviewManager();
    }
}

if (!function_exists('render_stars')) {
    /**
     * Render unicode star rating representation (e.g. ★★★★☆).
     */
    function render_stars(float|int $rating, int $max = 5): string
    {
        $rounded = (int) round((float) $rating);
        $full = max(0, min($max, $rounded));
        $empty = max(0, $max - $full);

        return str_repeat('★', $full) . str_repeat('☆', $empty);
    }
}
