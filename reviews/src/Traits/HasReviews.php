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
        // AVG() in the database instead of loading every review
        $query = $approvedOnly ? $this->approvedReviews() : $this->reviews();
        return round((float) ($query->avg('rating') ?? 0.0), 1);
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

        // One GROUP BY query instead of loading every review
        $query = $approvedOnly ? $this->approvedReviews() : $this->reviews();
        $rows = $query->select('rating', 'COUNT(*) AS n')->groupBy('rating')->get();

        foreach ($rows as $row) {
            $r = (int) $row->rating;
            if (isset($dist[$r])) {
                $dist[$r] += (int) $row->n;
            }
        }

        return $dist;
    }

    /**
     * Get percentage of reviews with a specific star rating.
     */
    public function ratingPercentage(int $star, bool $approvedOnly = true, ?array $distribution = null, ?int $total = null): float
    {
        if ($distribution !== null && $total !== null) {
            if ($total === 0) {
                return 0.0;
            }
            return round((($distribution[$star] ?? 0) / $total) * 100, 1);
        }

        $summary = $this->ratingSummary($approvedOnly);
        return $summary['percentages'][$star] ?? 0.0;
    }

    /**
     * Get complete rating summary (average, count, distribution, percentages) in a single database query.
     *
     * @return array{average: float, count: int, distribution: array<int, int>, percentages: array<int, float>}
     */
    public function ratingSummary(bool $approvedOnly = true): array
    {
        $min = function_exists('config') ? (int) config('reviews.min_rating', 1) : 1;
        $max = function_exists('config') ? (int) config('reviews.max_rating', 5) : 5;

        $dist = [];
        $percentages = [];
        for ($i = $max; $i >= $min; $i--) {
            $dist[$i] = 0;
            $percentages[$i] = 0.0;
        }

        $query = $approvedOnly ? $this->approvedReviews() : $this->reviews();
        $reviews = $query->get();
        $total = count($reviews);

        if ($total === 0) {
            return [
                'average' => 0.0,
                'count' => 0,
                'distribution' => $dist,
                'percentages' => $percentages,
            ];
        }

        $sum = 0;
        foreach ($reviews as $rev) {
            $r = (int) $rev->rating;
            $sum += $r;
            if (isset($dist[$r])) {
                $dist[$r]++;
            }
        }

        $average = round($sum / $total, 1);
        foreach ($dist as $star => $cnt) {
            $percentages[$star] = round(($cnt / $total) * 100, 1);
        }

        return [
            'average' => $average,
            'count' => $total,
            'distribution' => $dist,
            'percentages' => $percentages,
        ];
    }
}
