<?php

declare(strict_types=1);

namespace Bongo\Packages\Newsletter\Models;

use System\Database\Model;

class Subscriber extends Model
{
    protected static $table = 'newsletter_subscribers';

    protected array $fillable = [
        'id',
        'email',
        'name',
        'status',
        'token',
        'unsubscribe_token',
        'source',
        'ip_address',
        'verified_at',
        'unsubscribed_at',
    ];

    public static function tableName(): string
    {
        return function_exists('config') ? config('newsletter.table', 'newsletter_subscribers') : (static::$table ?? 'newsletter_subscribers');
    }

    public function isSubscribed(): bool
    {
        return $this->status === 'subscribed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isUnsubscribed(): bool
    {
        return $this->status === 'unsubscribed';
    }
}
