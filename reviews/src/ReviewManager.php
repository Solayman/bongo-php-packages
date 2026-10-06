<?php

declare(strict_types=1);

namespace Bongo\Packages\Reviews;

use Bongo\Packages\Reviews\Models\Review;
use System\Database\Model;
use System\Database\QueryBuilder;

class ReviewManager
{
    /**
     * Get query for all reviews.
     */
    public function query(): QueryBuilder
    {
        return Review::query();
    }

    /**
     * Get pending reviews awaiting moderation.
     *
     * @return Review[]
     */
    public function pending(): array
    {
        return Review::query()->where('status', 'pending')->latest()->get();
    }

    /**
     * Approve a review.
     */
    public function approve(int|string $reviewId): bool
    {
        $review = Review::find($reviewId);
        if ($review) {
            $review->approve();
            return true;
        }
        return false;
    }

    /**
     * Reject a review.
     */
    public function reject(int|string $reviewId): bool
    {
        $review = Review::find($reviewId);
        if ($review) {
            $review->reject();
            return true;
        }
        return false;
    }

    /**
     * Mark review as spam.
     */
    public function spam(int|string $reviewId): bool
    {
        $review = Review::find($reviewId);
        if ($review) {
            $review->spam();
            return true;
        }
        return false;
    }

    /**
     * Get reviews for a specific model.
     */
    public function for(Model $model): QueryBuilder
    {
        return Review::query()
            ->where('reviewable_type', get_class($model))
            ->where('reviewable_id', (string) $model->getKey());
    }

    /**
     * Get latest approved reviews across all models.
     *
     * @return Review[]
     */
    public function latest(int $limit = 10): array
    {
        return Review::query()->where('status', 'approved')->latest()->limit($limit)->get();
    }
}
