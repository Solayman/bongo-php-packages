<?php

declare(strict_types=1);

namespace Bongo\Packages\Reviews\Models;

use System\Database\Model;
use System\Database\BelongsTo;

class ReviewVote extends Model
{
    protected static $table = 'review_votes';

    protected array $fillable = [
        'id',
        'review_id',
        'user_id',
        'ip_address',
        'is_helpful',
    ];

    protected array $casts = [
        'review_id' => 'int',
        'user_id' => 'int',
        'is_helpful' => 'bool',
    ];

    public static function tableName(): string
    {
        return function_exists('config') ? config('reviews.votes_table', 'review_votes') : (static::$table ?? 'review_votes');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'review_id');
    }
}
