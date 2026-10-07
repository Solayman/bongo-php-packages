<?php

declare(strict_types=1);

namespace Bongo\Packages\Reviews\Models;

use System\Database\Model;
use System\Database\HasMany;

class Review extends Model
{
    protected static $table = 'reviews';

    protected array $fillable = [
        'id',
        'reviewable_type',
        'reviewable_id',
        'user_id',
        'author_name',
        'author_email',
        'rating',
        'title',
        'content',
        'status',
        'is_verified_buyer',
        'helpful_count',
        'unhelpful_count',
    ];

    protected array $casts = [
        'rating' => 'int',
        'is_verified_buyer' => 'bool',
        'helpful_count' => 'int',
        'unhelpful_count' => 'int',
        'user_id' => 'int',
    ];

    public static function tableName(): string
    {
        return function_exists('config') ? config('reviews.table', 'reviews') : (static::$table ?? 'reviews');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class, 'review_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVerified(): bool
    {
        return (bool) $this->is_verified_buyer;
    }

    public function approve(): self
    {
        $this->status = 'approved';
        $this->save();
        return $this;
    }

    public function reject(): self
    {
        $this->status = 'rejected';
        $this->save();
        return $this;
    }

    public function spam(): self
    {
        $this->status = 'spam';
        $this->save();
        return $this;
    }

    /**
     * Cast a helpful (or unhelpful) vote on this review.
     */
    public function vote(int|string|null $userId = null, bool $helpful = true, ?string $ip = null): bool
    {
        $userId = $userId !== null ? (int) $userId : null;
        if ($userId === null && ($ip === null || $ip === '')) {
            return false;   // a vote needs a voter: a user id or an IP address
        }

        // Check if already voted
        $query = ReviewVote::query()->where('review_id', $this->id);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->where('ip_address', $ip);
        }

        $existing = $query->first();
        if ($existing) {
            if ((bool) $existing->is_helpful === $helpful) {
                return false; // Already voted identically
            }
            $existing->is_helpful = $helpful;
            $existing->save();
            // Move one vote from the other counter, atomically (no lost updates under parallel votes)
            [$up, $down] = $helpful ? ['helpful_count', 'unhelpful_count'] : ['unhelpful_count', 'helpful_count'];
            static::query()->where('id', $this->id)->increment($up);
            static::query()->where('id', $this->id)->where($down, '>', 0)->decrement($down);
            $this->refreshCounts();
            return true;
        }

        // New vote
        ReviewVote::create([
            'review_id' => $this->id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'is_helpful' => $helpful,
        ]);

        static::query()->where('id', $this->id)->increment($helpful ? 'helpful_count' : 'unhelpful_count');
        $this->refreshCounts();

        return true;
    }

    /** Reload the vote counters after an atomic update */
    protected function refreshCounts(): void
    {
        $row = static::query()->where('id', $this->id)->first();
        if ($row) {
            $this->helpful_count = (int) $row->helpful_count;
            $this->unhelpful_count = (int) $row->unhelpful_count;
        }
    }

    /**
     * Retrieve the parent model this review belongs to.
     */
    public function reviewable(): ?Model
    {
        $class = $this->reviewable_type;
        if (class_exists($class) && is_subclass_of($class, Model::class)) {
            return $class::find($this->reviewable_id);
        }
        return null;
    }
}
