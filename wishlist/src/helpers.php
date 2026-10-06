<?php

declare(strict_types=1);

use Bongo\Packages\Wishlist\Wishlist;

if (!function_exists('wishlist')) {
    /**
     * Get or create a wishlist instance.
     */
    function wishlist(?string $name = null): Wishlist
    {
        return Wishlist::make($name);
    }
}

if (!function_exists('wishlist_has')) {
    /**
     * Check if an item exists in the default or named wishlist.
     */
    function wishlist_has(mixed $item, ?string $name = null): bool
    {
        return Wishlist::make($name)->has($item);
    }
}

if (!function_exists('wishlist_count')) {
    /**
     * Get total item count in the default or named wishlist.
     */
    function wishlist_count(?string $name = null): int
    {
        return Wishlist::make($name)->count();
    }
}
