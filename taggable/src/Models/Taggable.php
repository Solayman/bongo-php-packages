<?php

declare(strict_types=1);

namespace Bongo\Packages\Taggable\Models;

use System\Database\Model;
use System\Database\BelongsTo;

class Taggable extends Model
{
    protected static $table = 'taggables';

    protected array $fillable = [
        'id',
        'tag_id',
        'taggable_type',
        'taggable_id',
    ];

    protected array $casts = [
        'tag_id' => 'int',
    ];

    public static function tableName(): string
    {
        return function_exists('config') ? config('taggable.taggables_table', 'taggables') : (static::$table ?? 'taggables');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}
