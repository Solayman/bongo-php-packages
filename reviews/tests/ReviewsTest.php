<?php

declare(strict_types=1);

namespace Bongo\Packages\Reviews\Tests;

use Bongo\Packages\Reviews\Models\Review;
use Bongo\Packages\Reviews\Models\ReviewVote;
use Bongo\Packages\Reviews\ReviewManager;
use Bongo\Packages\Reviews\Traits\HasReviews;
use InvalidArgumentException;
use System\Database\Model;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

require_once dirname(__DIR__) . '/src/helpers.php';

class TestReviewProduct extends Model
{
    use HasReviews;

    protected static $table = 'test_review_products';
    protected array $fillable = ['id', 'name', 'price'];
}

class TestReviewUser extends Model
{
    protected static $table = 'test_review_users';
    protected array $fillable = ['id', 'name', 'email'];
}

class ReviewsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('review_votes');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('test_review_products');
        Schema::dropIfExists('test_review_users');

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reviewable_type', 255);
            $table->string('reviewable_id', 100);
            $table->foreignId('user_id')->nullable();
            $table->string('author_name', 100)->nullable();
            $table->string('author_email', 150)->nullable();
            $table->integer('rating');
            $table->string('title', 255)->nullable();
            $table->text('content')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_verified_buyer')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->integer('unhelpful_count')->default(0);
            $table->timestamps();
        });

        Schema::create('review_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id');
            $table->foreignId('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_helpful')->default(true);
            $table->timestamps();
        });

        Schema::create('test_review_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });

        Schema::create('test_review_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
    }

    public function testAddReviewAndValidation(): void
    {
        $product = TestReviewProduct::create(['name' => 'Studio Headphones', 'price' => 199.99]);
        $user = TestReviewUser::create(['name' => 'Rahim', 'email' => 'rahim@example.com']);

        $review = $product->addReview(
            user: $user,
            rating: 5,
            title: 'Incredible Sound Quality!',
            content: 'Deep bass and clear highs.',
            verified: true
        );

        $this->assertSame(5, $review->rating);
        $this->assertSame('Incredible Sound Quality!', $review->title);
        $this->assertTrue($review->isVerified());
        $this->assertSame('Rahim', $review->author_name);
        $this->assertSame('pending', $review->status);

        // Rating bounds validation
        $this->assertThrows(InvalidArgumentException::class, function () use ($product, $user) {
            $product->addReview($user, 6);
        });

        $this->assertThrows(InvalidArgumentException::class, function () use ($product, $user) {
            $product->addReview($user, 0);
        });
    }

    public function testAverageRatingAndCount(): void
    {
        $product = TestReviewProduct::create(['name' => 'Wireless Keyboard', 'price' => 45.00]);

        // No reviews yet
        $this->assertSame(0.0, $product->averageRating());
        $this->assertSame(0, $product->reviewsCount());

        $r1 = $product->addReview(1, 5, 'Great');
        $r2 = $product->addReview(2, 4, 'Good');
        $r3 = $product->addReview(3, 3, 'Okay');

        // Unapproved reviews shouldn't count in approved average
        $this->assertSame(0.0, $product->averageRating(approvedOnly: true));

        // When including all
        $this->assertSame(4.0, $product->averageRating(approvedOnly: false));
        $this->assertSame(3, $product->reviewsCount(approvedOnly: false));

        // Approve 2 of them
        $r1->approve();
        $r2->approve();

        $this->assertSame(4.5, $product->averageRating(approvedOnly: true));
        $this->assertSame(2, $product->reviewsCount(approvedOnly: true));
    }

    public function testRatingDistributionAndPercentage(): void
    {
        $product = TestReviewProduct::create(['name' => 'Smart Watch', 'price' => 120.00]);

        $r1 = $product->addReview(1, 5);
        $r2 = $product->addReview(2, 5);
        $r3 = $product->addReview(3, 4);
        $r4 = $product->addReview(4, 1);

        $r1->approve();
        $r2->approve();
        $r3->approve();
        $r4->approve();

        $dist = $product->ratingDistribution();
        $this->assertSame(2, $dist[5]);
        $this->assertSame(1, $dist[4]);
        $this->assertSame(0, $dist[3]);
        $this->assertSame(0, $dist[2]);
        $this->assertSame(1, $dist[1]);

        $this->assertSame(50.0, $product->ratingPercentage(5));
        $this->assertSame(25.0, $product->ratingPercentage(4));
        $this->assertSame(0.0, $product->ratingPercentage(3));
    }

    public function testModerationWorkflow(): void
    {
        $product = TestReviewProduct::create(['name' => 'Backpack', 'price' => 40.00]);
        $review = $product->addReview(1, 4, 'Nice bag');

        $this->assertTrue($review->isPending());
        $this->assertFalse($review->isApproved());

        $manager = new ReviewManager();
        $pending = $manager->pending();
        $this->assertCount(1, $pending);

        $manager->approve($review->id);
        $review = Review::find($review->id);
        $this->assertTrue($review->isApproved());
        $this->assertFalse($review->isPending());

        $manager->spam($review->id);
        $review = Review::find($review->id);
        $this->assertSame('spam', $review->status);
    }

    public function testHelpfulVoting(): void
    {
        $product = TestReviewProduct::create(['name' => 'Mousepad', 'price' => 15.00]);
        $review = $product->addReview(1, 5, 'Smooth');

        $this->assertSame(0, $review->helpful_count);
        $this->assertSame(0, $review->unhelpful_count);

        // User 2 votes helpful
        $voted = $review->vote(userId: 2, helpful: true);
        $this->assertTrue($voted);
        $this->assertSame(1, $review->helpful_count);

        // Duplicate identical vote ignored
        $votedAgain = $review->vote(userId: 2, helpful: true);
        $this->assertFalse($votedAgain);
        $this->assertSame(1, $review->helpful_count);

        // User 2 flips vote to unhelpful
        $flipped = $review->vote(userId: 2, helpful: false);
        $this->assertTrue($flipped);
        $this->assertSame(0, $review->helpful_count);
        $this->assertSame(1, $review->unhelpful_count);

        // User 3 votes helpful
        $review->vote(userId: 3, helpful: true);
        $this->assertSame(1, $review->helpful_count);
        $this->assertSame(1, $review->unhelpful_count);
    }

    public function testStarRenderingAndHelpers(): void
    {
        $this->assertSame('★★★★★', render_stars(5));
        $this->assertSame('★★★★☆', render_stars(4.2));
        $this->assertSame('★★★☆☆', render_stars(3.1));
        $this->assertSame('☆☆☆☆☆', render_stars(0));

        $mgr = reviews();
        $this->assertNotNull($mgr);
        $this->assertTrue($mgr instanceof ReviewManager);
    }

    public function testRatingSummary(): void
    {
        $product = TestReviewProduct::create(['name' => 'Tablet', 'price' => 200.00]);
        $r1 = $product->addReview(1, 5);
        $r2 = $product->addReview(2, 4);
        $r1->approve();
        $r2->approve();

        $summary = $product->ratingSummary(approvedOnly: true);
        $this->assertSame(4.5, $summary['average']);
        $this->assertSame(2, $summary['count']);
        $this->assertSame(1, $summary['distribution'][5]);
        $this->assertSame(1, $summary['distribution'][4]);
        $this->assertSame(50.0, $summary['percentages'][5]);
        $this->assertSame(50.0, $summary['percentages'][4]);
    }

    public function testRelationsUseTheCoreRelationClasses(): void
    {
        $this->assertTrue((new \Bongo\Packages\Reviews\Models\Review())->votes() instanceof \System\Database\HasMany, 'votes() returns a core HasMany');
        $this->assertTrue((new \Bongo\Packages\Reviews\Models\ReviewVote())->review() instanceof \System\Database\BelongsTo, 'review() returns a core BelongsTo');
    }
}
