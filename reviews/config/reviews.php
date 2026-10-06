<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Database tables used for reviews and helpful upvote/downvote tracking.
    |
    */
    'table' => 'reviews',
    'votes_table' => 'review_votes',

    /*
    |--------------------------------------------------------------------------
    | Moderation Settings
    |--------------------------------------------------------------------------
    |
    | When auto_approve is true, new reviews are automatically given 'approved'
    | status. When false, they start as 'pending' until moderated.
    |
    */
    'auto_approve' => false,

    /*
    |--------------------------------------------------------------------------
    | Rating Constraints
    |--------------------------------------------------------------------------
    |
    | Minimum and maximum numeric ratings allowed (typically 1 to 5).
    |
    */
    'min_rating' => 1,
    'max_rating' => 5,

    /*
    |--------------------------------------------------------------------------
    | Guest Reviews
    |--------------------------------------------------------------------------
    |
    | Whether unauthenticated guests can submit reviews.
    |
    */
    'allow_guest' => false,
];
