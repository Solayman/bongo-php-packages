# Bongo Reviews & Ratings Package

A zero-dependency 5-star customer ratings and reviews package for the Bongo PHP framework.

## Features
- **Polymorphic / Universal**: Add ratings and reviews to any model (`Product`, `Course`, `Post`, etc.).
- **Verified Buyer Badges**: Highlight authentic verified purchases.
- **Helpful Voting**: Community upvotes and downvotes with automatic vote flipping and deduplication.
- **Moderation Workflow**: Moderate reviews with `approved`, `pending`, `rejected`, and `spam` statuses.
- **Rating Metrics**: Instant calculation of average ratings, review counts, star distribution (1–5 stars), and star percentages.
- **Unicode Star Rendering**: Helper `render_stars(4.5)` returns clean star representations (e.g. `★★★★★`, `★★★★☆`).

## Installation & Configuration
Publish configuration and run migrations:
```bash
php bongo migrate
```

Configuration in `config/reviews.php`:
```php
return [
    'table' => 'reviews',
    'votes_table' => 'review_votes',
    'auto_approve' => false,
    'min_rating' => 1,
    'max_rating' => 5,
    'allow_guest' => false,
];
```

## Basic Usage

Add the `HasReviews` trait to your model:

```php
use Bongo\Packages\Reviews\Traits\HasReviews;
use System\Database\Model;

class Product extends Model
{
    use HasReviews;
}
```

Add reviews and calculate metrics:

```php
// Add review
$review = $product->addReview(
    user: $user,
    rating: 5,
    title: 'Fantastic product!',
    content: 'Exceeded all expectations.',
    verified: true
);

// Metrics
$product->averageRating(); // e.g. 4.8
$product->reviewsCount();  // e.g. 24
$product->ratingDistribution(); // [5 => 20, 4 => 3, 3 => 1, 2 => 0, 1 => 0]
$product->ratingPercentage(5);  // 83.3%

// Moderation
$review->approve();
$review->reject();
$review->spam();

// Helpful votes
$review->vote(userId: $user->id, helpful: true);
```
