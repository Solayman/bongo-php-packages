<?php

declare(strict_types=1);

namespace Bongo\Packages\Sluggable\Tests;

use Bongo\Packages\Sluggable\Slugger;
use Bongo\Packages\Sluggable\Traits\HasSlug;
use System\Database\Model;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

class TestPost extends Model
{
    use HasSlug;

    protected static $table = 'test_sluggable_posts';
    protected array $fillable = ['id', 'title', 'slug', 'subtitle'];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
                'separator' => '-',
                'on_update' => false,
            ],
        ];
    }
}

class SluggableTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/src/helpers.php';

        Schema::dropIfExists('test_sluggable_posts');
        Schema::create('test_sluggable_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });
    }

    public function tearDown(): void
    {
        Schema::dropIfExists('test_sluggable_posts');
        parent::tearDown();
    }

    public function testBasicEnglishSlug(): void
    {
        $this->assertSame('hello-world', str_slug('Hello World!'));
        $this->assertSame('bongo-php-framework-100', str_slug('Bongo PHP Framework 100%'));
        $this->assertSame('clean-and-simple-slug', str_slug('---Clean  &  Simple Slug---'));
    }

    public function testBengaliUnicodeSlug(): void
    {
        $bengali = 'বংগো পিএইচপি ফ্রেমওয়ার্ক টিউটোরিয়াল';
        $slug = str_slug($bengali);

        $this->assertSame('বংগো-পিএইচপি-ফ্রেমওয়ার্ক-টিউটোরিয়াল', $slug);
        $this->assertFalse(str_contains($slug, ' '));
    }

    public function testMixedAlphanumericAndCustomSeparator(): void
    {
        $title = 'Bongo MVC 2026: Fast & Pure PHP!';
        $slug = str_slug($title, '_');

        $this->assertSame('bongo_mvc_2026_fast_and_pure_php', $slug);
    }

    public function testMaxLengthTruncation(): void
    {
        $longTitle = 'This is an excessively long title designed specifically to test the maximum length boundary of the slug generator';
        $slug = str_slug($longTitle, '-', 30);

        $this->assertTrue(strlen($slug) <= 30);
        $this->assertFalse(str_ends_with($slug, '-'));
    }

    public function testMakeUniqueCollisions(): void
    {
        $existing = ['product', 'product-1', 'product-2'];
        $existsChecker = fn(string $test) => in_array($test, $existing, true);

        $unique = Slugger::makeUnique('product', $existsChecker);
        $this->assertSame('product-3', $unique);
    }

    public function testReservedSlugAvoidance(): void
    {
        $existsChecker = fn(string $test) => false;
        $unique = Slugger::makeUnique('admin', $existsChecker, '-', ['admin']);

        $this->assertSame('admin-1', $unique);
    }

    public function testModelHasSlugTraitLifecycle(): void
    {
        $post1 = new TestPost(['title' => 'First Awesome Article']);
        $post1->generateSlug();
        $post1->save();

        $this->assertSame('first-awesome-article', $post1->slug);

        // Second article with duplicate title should automatically become unique
        $post2 = new TestPost(['title' => 'First Awesome Article']);
        $post2->generateSlug();
        $post2->save();

        $this->assertSame('first-awesome-article-1', $post2->slug);

        // Test findBySlug
        $found = TestPost::findBySlug('first-awesome-article');
        $this->assertNotNull($found);
        $this->assertSame((int) $post1->id, (int) $found->id);

        $found2 = TestPost::findBySlug('first-awesome-article-1');
        $this->assertNotNull($found2);
        $this->assertSame((int) $post2->id, (int) $found2->id);

        $this->assertNull(TestPost::findBySlug('non-existent-article'));
    }
}
