<?php

declare(strict_types=1);

namespace Bongo\Packages\Reviews\Models;

use System\Database\Model;
use System\Database\Relations\HasMany;

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

        // Check if already voted
        $query = ReviewVote::query()->where('review_id', $this->id);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        } elseif ($ip !== null) {
            $query->where('ip_address', $ip);
        }

        $existing = $query->first();
        if ($existing) {
            if ($existing->is_helpful === $helpful) {
                return false; // Already voted identically
            }
            // Swap vote
            if ($helpful) {
                $this->helpful_count++;
                $this->unhelpful_count = max(0, $this->unhelpful_count - 1);
            } else {
                $this->unhelpful_count++;
                $this->helpful_count = max(0, $this->helpful_count - 1);
            }
            $existing->is_helpful = $helpful;
            $existing->save();
            $this->save();
            return true;
        }

        // New vote
        ReviewVote::create([
            'review_id' => $this->id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'is_helpful' => $helpful,
        ]);

        if ($helpful) {
            $this->helpful_count++;
        } else {
            $this->unhelpful_count++;
        }
        $this->save();

        return true;
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
