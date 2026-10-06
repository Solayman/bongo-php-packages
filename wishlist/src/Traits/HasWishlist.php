<?php

declare(strict_types=1);

namespace Bongo\Packages\Wishlist\Traits;

use Bongo\Packages\Wishlist\Models\Wishlist as WishlistModel;
use Bongo\Packages\Wishlist\Models\WishlistItem;
use Bongo\Packages\Wishlist\Wishlist;
use System\Database\Relations\HasMany;

trait HasWishlist
{
    /**
     * Get all wishlist records belonging to this user.
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(WishlistModel::class, 'user_id');
    }

    /**
     * Get the Wishlist service instance for this user.
     */
    public function wishlist(?string $name = null): Wishlist
    {
        return Wishlist::forUser($this, $name);
    }

    /**
     * Add item to wishlist.
     */
    public function addToWishlist(mixed $item, int $quantity = 1, ?float $price = null, array $options = [], ?string $name = null): WishlistItem
    {
        return $this->wishlist($name)->add($item, $quantity, $price, $options);
    }

    /**
     * Remove item from wishlist.
     */
    public function removeFromWishlist(mixed $item, ?string $name = null): bool
    {
        return $this->wishlist($name)->remove($item);
    }

    /**
     * Check if item is in wishlist.
     */
    public function hasInWishlist(mixed $item, ?string $name = null): bool
    {
        return $this->wishlist($name)->has($item);
    }

    /**
     * Total item count in wishlist.
     */
    public function wishlistCount(?string $name = null): int
    {
        return $this->wishlist($name)->count();
    }
}
