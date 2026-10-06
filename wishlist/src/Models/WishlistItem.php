<?php

declare(strict_types=1);

namespace Bongo\Packages\Wishlist\Models;

use System\Database\Model;
use System\Database\BelongsTo;

class WishlistItem extends Model
{
    protected static $table = 'wishlist_items';

    protected array $fillable = [
        'id',
        'wishlist_id',
        'item_type',
        'item_id',
        'quantity',
        'price',
        'options',
    ];

    protected array $casts = [
        'wishlist_id' => 'int',
        'quantity' => 'int',
        'price' => 'float',
        'options' => 'array',
    ];

    public static function tableName(): string
    {
        return function_exists('config') ? config('wishlist.items_table', 'wishlist_items') : (static::$table ?? 'wishlist_items');
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class, 'wishlist_id');
    }

    public function getItemModel(): ?Model
    {
        $class = $this->item_type;
        if (class_exists($class) && is_subclass_of($class, Model::class)) {
            return $class::find($this->item_id);
        }
        return null;
    }
}
