<?php

declare(strict_types=1);

namespace Bongo\Packages\Wishlist\Tests;

use Bongo\Packages\Wishlist\Models\Wishlist as WishlistModel;
use Bongo\Packages\Wishlist\Models\WishlistItem as ItemModel;
use Bongo\Packages\Wishlist\Traits\HasWishlist;
use Bongo\Packages\Wishlist\Wishlist;
use System\Database\Model;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

require_once dirname(__DIR__) . '/src/helpers.php';

class TestWishlistUser extends Model
{
    use HasWishlist;

    protected static $table = 'test_wishlist_users';
    protected array $fillable = ['id', 'name', 'email'];
}

class TestWishlistProduct extends Model
{
    protected static $table = 'test_wishlist_products';
    protected array $fillable = ['id', 'name', 'price'];
}

class WishlistTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Wishlist::stopFaking();

        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('test_wishlist_users');
        Schema::dropIfExists('test_wishlist_products');

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('name')->default('default');
            $table->string('share_token')->nullable()->unique();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id');
            $table->string('item_type');
            $table->string('item_id');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->text('options')->nullable();
            $table->timestamps();
        });

        Schema::create('test_wishlist_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('test_wishlist_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    public function testDatabaseWishlistAddAndCount(): void
    {
        $product = TestWishlistProduct::create(['name' => 'Mechanical Keyboard', 'price' => 120.00]);
        $wishlist = Wishlist::forUser(1);

        $this->assertSame(0, $wishlist->count());
        $this->assertFalse($wishlist->has($product));

        $item = $wishlist->add($product, 1, 120.00, ['color' => 'Black']);

        $this->assertSame(1, $wishlist->count());
        $this->assertTrue($wishlist->has($product));
        $this->assertSame('Mechanical Keyboard', $item->getItemModel()->name);
        $this->assertSame(['color' => 'Black'], $item->options);
    }

    public function testWishlistDuplicateItemIncrementsQuantity(): void
    {
        $product = TestWishlistProduct::create(['name' => 'Gaming Mouse', 'price' => 50.00]);
        $wishlist = Wishlist::forUser(2);

        $wishlist->add($product, 2);
        $wishlist->add($product, 3);

        $this->assertSame(1, $wishlist->count());
        $items = $wishlist->items();
        $this->assertCount(1, $items);
        $this->assertSame(5, $items[0]->quantity);
    }

    public function testWishlistRemoveAndClear(): void
    {
        $prod1 = TestWishlistProduct::create(['name' => 'Monitor', 'price' => 300.00]);
        $prod2 = TestWishlistProduct::create(['name' => 'Webcam', 'price' => 80.00]);

        $wishlist = Wishlist::forUser(3);
        $wishlist->add($prod1);
        $wishlist->add($prod2);

        $this->assertSame(2, $wishlist->count());

        $removed = $wishlist->remove($prod1);
        $this->assertTrue($removed);
        $this->assertSame(1, $wishlist->count());
        $this->assertFalse($wishlist->has($prod1));
        $this->assertTrue($wishlist->has($prod2));

        $wishlist->clear();
        $this->assertSame(0, $wishlist->count());
    }

    public function testMultipleNamedWishlists(): void
    {
        $p1 = TestWishlistProduct::create(['name' => 'Sneakers', 'price' => 90.00]);
        $p2 = TestWishlistProduct::create(['name' => 'Headphones', 'price' => 150.00]);

        $defaultList = Wishlist::forUser(4);
        $holidayList = Wishlist::forUser(4, 'holiday');

        $defaultList->add($p1);
        $holidayList->add($p2);

        $this->assertSame(1, $defaultList->count());
        $this->assertSame(1, $holidayList->count());
        $this->assertTrue($defaultList->has($p1));
        $this->assertFalse($defaultList->has($p2));
        $this->assertTrue($holidayList->has($p2));
    }

    public function testShareableTokenAndPublicAccess(): void
    {
        $wishlist = Wishlist::forUser(5);
        $p = TestWishlistProduct::create(['name' => 'Coffee Maker', 'price' => 75.00]);
        $wishlist->add($p);

        $shareUrl = $wishlist->getShareUrl();
        $token = $wishlist->shareToken();

        $this->assertContains('/wishlist/shared/', $shareUrl);
        $this->assertContains($token, $shareUrl);

        $this->assertFalse($wishlist->isPublic());
        $wishlist->makePublic();
        $this->assertTrue($wishlist->isPublic());

        $found = Wishlist::findByShareToken($token);
        $this->assertNotNull($found);
        $this->assertTrue($found->has($p));
    }

    public function testGuestSessionMergeToUser(): void
    {
        $sessionId = 'sess_xyz123';
        $guestWishlist = Wishlist::forSession($sessionId);

        $p1 = TestWishlistProduct::create(['name' => 'Desk Lamp', 'price' => 35.00]);
        $p2 = TestWishlistProduct::create(['name' => 'Office Chair', 'price' => 200.00]);

        $guestWishlist->add($p1, 2);
        $guestWishlist->add($p2, 1);

        $this->assertSame(2, $guestWishlist->count());

        // Now user logs in with user_id = 99
        Wishlist::mergeGuestToUser($sessionId, 99);

        $userWishlist = Wishlist::forUser(99);
        $this->assertSame(2, $userWishlist->count());
        $this->assertTrue($userWishlist->has($p1));
        $this->assertTrue($userWishlist->has($p2));
    }

    public function testHasWishlistTraitOnUser(): void
    {
        $user = TestWishlistUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $product = TestWishlistProduct::create(['name' => 'Book', 'price' => 25.00]);

        $this->assertSame(0, $user->wishlistCount());
        $user->addToWishlist($product, 1, 25.00);

        $this->assertSame(1, $user->wishlistCount());
        $this->assertTrue($user->hasInWishlist($product));

        $user->removeFromWishlist($product);
        $this->assertSame(0, $user->wishlistCount());
    }

    public function testFakeModeAndHelpers(): void
    {
        Wishlist::fake();

        $wish = wishlist();
        $this->assertSame(0, $wish->count());

        $wish->add(['item_type' => 'book', 'item_id' => '101', 'price' => 15.00]);
        $this->assertSame(1, $wish->count());
        $this->assertTrue(wishlist_has(['item_type' => 'book', 'item_id' => '101']));
        $this->assertSame(1, wishlist_count());

        $wish->remove(['item_type' => 'book', 'item_id' => '101']);
        $this->assertSame(0, wishlist_count());
    }

    public function testItemMemoization(): void
    {
        $wish = Wishlist::forSession('sess_memo_1', 'memo_list');
        $wish->add('item_1', 2, 10.0);
        $wish->add('item_2', 1, 20.0);

        // Fetch items - caches in-memory
        $items1 = $wish->items();
        $this->assertCount(2, $items1);

        // Subsequent count() and has() use cache without SQL
        $this->assertSame(2, $wish->count());
        $this->assertTrue($wish->has('item_1'));
        $this->assertTrue($wish->has('item_2'));
        $this->assertFalse($wish->has('item_3'));

        // Removing item clears memoization
        $wish->remove('item_1');
        $this->assertSame(1, $wish->count());
        $this->assertFalse($wish->has('item_1'));
    }

    public function testRelationsUseTheCoreRelationClasses(): void
    {
        $this->assertTrue((new \Bongo\Packages\Wishlist\Models\Wishlist())->items() instanceof \System\Database\HasMany, 'items() returns a core HasMany');
        $this->assertTrue((new \Bongo\Packages\Wishlist\Models\WishlistItem())->wishlist() instanceof \System\Database\BelongsTo, 'wishlist() returns a core BelongsTo');
    }
}
