<?php

declare(strict_types=1);

namespace Bongo\Packages\Reviews\Traits;

use Bongo\Packages\Reviews\Models\Review;
use InvalidArgumentException;
use System\Database\Model;
use System\Database\QueryBuilder;

trait HasReviews
{
    /**
     * Get all reviews for this model.
     */
    public function reviews(): QueryBuilder
    {
        return Review::query()
            ->where('reviewable_type', static::class)
            ->where('reviewable_id', (string) $this->getKey());
    }

    /**
     * Get approved reviews for this model.
     */
    public function approvedReviews(): QueryBuilder
    {
        return $this->reviews()->where('status', 'approved');
    }

    /**
     * Add a review to this model.
     */
    public function addReview(
        Model|int|string|null $user,
        int $rating,
        ?string $title = null,
        ?string $content = null,
        bool $verified = false,
        ?string $authorName = null,
        ?string $authorEmail = null
    ): Review {
        $min = function_exists('config') ? (int) config('reviews.min_rating', 1) : 1;
        $max = function_exists('config') ? (int) config('reviews.max_rating', 5) : 5;

        if ($rating < $min || $rating > $max) {
            throw new InvalidArgumentException("Rating must be between $min and $max stars.");
        }

        $userId = null;
        if ($user instanceof Model) {
            $userId = (int) $user->getKey();
            $authorName ??= $user->name ?? null;
            $authorEmail ??= $user->email ?? null;
        } elseif ($user !== null) {
            $userId = (int) $user;
        }

        $autoApprove = function_exists('config') ? (bool) config('reviews.auto_approve', false) : false;
        $status = $autoApprove ? 'approved' : 'pending';

        return Review::create([
            'reviewable_type' => static::class,
            'reviewable_id' => (string) $this->getKey(),
            'user_id' => $userId,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'rating' => $rating,
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'is_verified_buyer' => $verified,
            'helpful_count' => 0,
            'unhelpful_count' => 0,
        ]);
    }

    /**
     * Get the average star rating.
     */
    public function averageRating(bool $approvedOnly = true): float
    {
        $query = $approvedOnly ? $this->approvedReviews() : $this->reviews();
        $reviews = $query->get();

        if (empty($reviews)) {
            return 0.0;
        }

        $total = array_sum(array_map(fn($r) => (int) $r->rating, $reviews));
        return round($total / count($reviews), 1);
    }

    /**
     * Get total review count.
     */
    public function reviewsCount(bool $approvedOnly = true): int
    {
        $query = $approvedOnly ? $this->approvedReviews() : $this->reviews();
        return $query->count();
    }

    /**
     * Get star rating distribution counts.
     *
     * @return array<int, int> [5 => count, 4 => count, 3 => count, 2 => count, 1 => count]
     */
    public function ratingDistribution(bool $approvedOnly = true): array
    {
        $min = function_exists('config') ? (int) config('reviews.min_rating', 1) : 1;
        $max = function_exists('config') ? (int) config('reviews.max_rating', 5) : 5;

        $dist = [];
        for ($i = $max; $i >= $min; $i--) {
            $dist[$i] = 0;
        }

        $query = $approvedOnly ? $this->approvedReviews() : $this->reviews();
        $reviews = $query->get();

        foreach ($reviews as $rev) {
            $r = (int) $rev->rating;
            if (isset($dist[$r])) {
                $dist[$r]++;
            }
        }

        return $dist;
    }

    /**
     * Get percentage of reviews with a specific star rating.
     */
    public function ratingPercentage(int $star, bool $approvedOnly = true): float
    {
        $total = $this->reviewsCount($approvedOnly);
        if ($total === 0) {
            return 0.0;
        }

        $dist = $this->ratingDistribution($approvedOnly);
        $count = $dist[$star] ?? 0;

        return round(($count / $total) * 100, 1);
    }
}
