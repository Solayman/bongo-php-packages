<?php

declare(strict_types=1);

namespace Bongo\Packages\Taggable\Tests;

use Bongo\Packages\Taggable\Models\Tag;
use Bongo\Packages\Taggable\Models\Taggable;
use Bongo\Packages\Taggable\Traits\HasTags;
use System\Database\Model;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

require_once dirname(__DIR__) . '/src/helpers.php';

class TestTaggablePost extends Model
{
    use HasTags;

    protected static $table = 'test_taggable_posts';
    protected array $fillable = ['id', 'title', 'content'];
}

class TestTaggableProduct extends Model
{
    use HasTags;

    protected static $table = 'test_taggable_products';
    protected array $fillable = ['id', 'name', 'price'];
}

class TaggableTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('test_taggable_posts');
        Schema::dropIfExists('test_taggable_products');

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('type', 50)->nullable();
            $table->text('description')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id');
            $table->string('taggable_type', 255);
            $table->string('taggable_id', 100);
            $table->timestamps();
        });

        Schema::create('test_taggable_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('content')->nullable();
            $table->timestamps();
        });

        Schema::create('test_taggable_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function tearDown(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('test_taggable_posts');
        Schema::dropIfExists('test_taggable_products');
        parent::tearDown();
    }

    public function testTagCreationAndBengaliUnicodeSlug(): void
    {
        $tag1 = Tag::findOrCreate('Web Development');
        $this->assertEquals('Web Development', $tag1->name);
        $this->assertEquals('web-development', $tag1->slug);

        // Deduplication returns same instance
        $tag2 = Tag::findOrCreate('Web Development');
        $this->assertEquals($tag1->id, $tag2->id);

        // Bengali Unicode tag
        $bengaliTag = Tag::findOrCreate('বাংলা প্রোগ্রামিং');
        $this->assertEquals('বাংলা প্রোগ্রামিং', $bengaliTag->name);
        $this->assertTrue(!empty($bengaliTag->slug));
    }

    public function testAttachAndQueryTagsOnModel(): void
    {
        $post = new TestTaggablePost(['title' => 'First Article']);
        $post->save();

        $post->tag('PHP, Framework, Bongo');

        $this->assertCount(3, $post->tags());
        $this->assertEquals(['Bongo', 'Framework', 'PHP'], $post->tagNames());
        $this->assertTrue($post->hasTag('PHP'));
        $this->assertTrue($post->hasTag('framework'));
        $this->assertFalse($post->hasTag('Ruby'));

        $this->assertTrue($post->hasAnyTag(['Ruby', 'PHP']));
        $this->assertTrue($post->hasAllTags(['PHP', 'Bongo']));
        $this->assertFalse($post->hasAllTags(['PHP', 'Ruby']));

        $this->assertEquals('Bongo, Framework, PHP', $post->tagsAsString());

        // Check Tag count updated
        $phpTag = Tag::query()->where('slug', 'php')->first();
        $this->assertEquals(1, $phpTag->count);
    }

    public function testUntagAndRetag(): void
    {
        $post = new TestTaggablePost(['title' => 'Untag Test']);
        $post->save();

        $post->tag(['Design', 'UI', 'UX']);
        $this->assertCount(3, $post->tags());

        // Untag specific tag
        $post->untag('UI');
        $this->assertCount(2, $post->tags());
        $this->assertFalse($post->hasTag('UI'));
        $this->assertTrue($post->hasTag('Design'));

        $uiTag = Tag::query()->where('slug', 'ui')->first();
        $this->assertEquals(0, $uiTag->count);

        // Retag replaces all tags
        $post->retag(['Mobile', 'Flutter']);
        $this->assertCount(2, $post->tags());
        $this->assertTrue($post->hasTag('Mobile'));
        $this->assertTrue($post->hasTag('Flutter'));
        $this->assertFalse($post->hasTag('Design'));

        // Untag all
        $post->untag();
        $this->assertCount(0, $post->tags());
    }

    public function testPolymorphicTaggingAcrossMultipleModels(): void
    {
        $post = new TestTaggablePost(['title' => 'Blog Post']);
        $post->save();

        $product = new TestTaggableProduct(['name' => 'Laptop', 'price' => 1200]);
        $product->save();

        $post->tag('Tech, Electronics');
        $product->tag('Electronics, Hardware');

        $this->assertTrue($post->hasTag('Tech'));
        $this->assertTrue($product->hasTag('Hardware'));
        $this->assertTrue($post->hasTag('Electronics'));
        $this->assertTrue($product->hasTag('Electronics'));

        // Tag usage count should be 2 for Electronics
        $elecTag = Tag::query()->where('slug', 'electronics')->first();
        $this->assertEquals(2, $elecTag->count);
    }

    public function testTagTypesIsolation(): void
    {
        $post = new TestTaggablePost(['title' => 'Typed Post']);
        $post->save();

        $post->tag('Technology', 'category');
        $post->tag('Trending', 'status');

        $categories = $post->tags('category');
        $this->assertCount(1, $categories);
        $this->assertEquals('Technology', $categories[0]->name);

        $statuses = $post->tags('status');
        $this->assertCount(1, $statuses);
        $this->assertEquals('Trending', $statuses[0]->name);
    }

    public function testPopularTagsAndTagCloud(): void
    {
        $post1 = new TestTaggablePost(['title' => 'Post 1']);
        $post1->save();
        $post1->tag('PHP, Web');

        $post2 = new TestTaggablePost(['title' => 'Post 2']);
        $post2->save();
        $post2->tag('PHP, Docker');

        $post3 = new TestTaggablePost(['title' => 'Post 3']);
        $post3->save();
        $post3->tag('PHP, Web, MySQL');

        $popular = Tag::popular(3);
        $this->assertTrue(!empty($popular));
        $this->assertEquals('PHP', $popular[0]->name);
        $this->assertEquals(3, $popular[0]->count);

        $cloud = Tag::tagCloud();
        $this->assertTrue(!empty($cloud));
        $phpEntry = array_values(array_filter($cloud, fn($c) => $c['slug'] === 'php'))[0];
        $this->assertEquals(28, $phpEntry['size']); // Highest frequency gets maxSize
        $this->assertEquals(1.0, $phpEntry['weight']);
    }

    public function testWithAnyTagAndWithAllTagsScopes(): void
    {
        $p1 = new TestTaggablePost(['title' => 'PHP & Vue']);
        $p1->save();
        $p1->tag('PHP, Vue');

        $p2 = new TestTaggablePost(['title' => 'PHP & React']);
        $p2->save();
        $p2->tag('PHP, React');

        $p3 = new TestTaggablePost(['title' => 'Python only']);
        $p3->save();
        $p3->tag('Python');

        // withAnyTag: matches p1 and p2
        $anyPhpReact = TestTaggablePost::withAnyTag(['React', 'Python'])->get();
        $this->assertCount(2, $anyPhpReact);

        // withAllTags: matches only p1
        $allPhpVue = TestTaggablePost::withAllTags(['PHP', 'Vue'])->get();
        $this->assertCount(1, $allPhpVue);
        $this->assertEquals('PHP & Vue', $allPhpVue[0]->title);
    }

    public function testXssSanitizationAndHelpers(): void
    {
        $dirtyName = '<script>alert(1)</script>Clean Tag';
        $tag = tag($dirtyName);
        $this->assertEquals('Clean Tag', $tag->name);
        $this->assertNotContains('<script>', $tag->name);

        $this->assertTrue(is_array(popular_tags()));
        $this->assertTrue(is_array(tag_cloud()));
    }

    public function testMemoizationAndBatchAttaching(): void
    {
        $post = new TestTaggablePost(['title' => 'Batch Test']);
        $post->save();

        // Batch attaching multiple tags
        $post->tag(['Batch1', 'Batch2', 'Batch3']);
        $this->assertCount(3, $post->tags());

        // Second call should return memoized tags
        $tags1 = $post->tags();
        $tags2 = $post->tags();
        $this->assertSame(count($tags1), count($tags2));

        // Untagging one resets memoization and updates count
        $post->untag('Batch2');
        $this->assertCount(2, $post->tags());
        $this->assertFalse($post->hasTag('Batch2'));
    }
}
