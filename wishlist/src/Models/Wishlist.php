<?php

declare(strict_types=1);

namespace Bongo\Packages\Wishlist\Models;

use System\Database\Model;
use System\Database\HasMany;

class Wishlist extends Model
{
    protected static $table = 'wishlists';

    protected array $fillable = [
        'id',
        'user_id',
        'session_id',
        'name',
        'share_token',
        'is_public',
    ];

    protected array $casts = [
        'is_public' => 'bool',
        'user_id' => 'int',
    ];

    public static function tableName(): string
    {
        return function_exists('config') ? config('wishlist.table', 'wishlists') : (static::$table ?? 'wishlists');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class, 'wishlist_id');
    }
}
