<?php

declare(strict_types=1);

namespace Bongo\Packages\ShoppingCart\Tests;

use Bongo\Packages\ShoppingCart\Cart;
use Bongo\Packages\ShoppingCart\CartItem;
use Bongo\Packages\ShoppingCart\Exceptions\CartException;
use System\Testing\TestCase;

class CartTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/src/helpers.php';
        Cart::fake();
    }

    public function testAddItemAndCounts(): void
    {
        $cart = cart();
        $this->assertTrue($cart->isEmpty());
        $this->assertSame(0, $cart->count());

        $item1 = $cart->add(1, 'Product A', 100.0, 2);
        $this->assertSame(2, $cart->count());
        $this->assertSame(1, $cart->countRows());
        $this->assertSame(200.0, $cart->subtotal());

        // Adding same item increments quantity
        $cart->add(1, 'Product A', 100.0, 1);
        $this->assertSame(3, $cart->count());
        $this->assertSame(1, $cart->countRows());
        $this->assertSame(300.0, $cart->subtotal());
    }

    public function testItemWithOptionsHasUniqueRowId(): void
    {
        $cart = cart();
        $item1 = $cart->add(10, 'T-Shirt', 500.0, 1, ['size' => 'M', 'color' => 'Blue']);
        $item2 = $cart->add(10, 'T-Shirt', 500.0, 1, ['size' => 'L', 'color' => 'Blue']);

        $this->assertSame(2, $cart->countRows());
        $this->assertNotSame($item1->rowId(), $item2->rowId());
        $this->assertSame(1000.0, $cart->subtotal());
    }

    public function testUpdateQuantityAndAttributes(): void
    {
        $cart = cart();
        $item = $cart->add(5, 'Keyboard', 1500.0, 1);

        // Update with integer
        $cart->update($item->rowId(), 3);
        $this->assertSame(3, $cart->count());
        $this->assertSame(4500.0, $cart->subtotal());

        // Update with array
        $cart->update($item->rowId(), ['price' => 1200.0, 'quantity' => 2]);
        $this->assertSame(2, $cart->count());
        $this->assertSame(2400.0, $cart->subtotal());

        // Update to 0 removes item
        $cart->update($item->rowId(), 0);
        $this->assertTrue($cart->isEmpty());
    }

    public function testTaxShippingDiscountAndTotal(): void
    {
        $cart = cart();
        $cart->add(1, 'Item 1', 1000.0, 1);

        $cart->setTaxRate(10.0); // 10% tax = 100.00
        $cart->setShipping(50.0);
        $cart->setDiscount(150.0);

        $this->assertSame(1000.0, $cart->subtotal());
        $this->assertSame(100.0, $cart->tax());
        $this->assertSame(50.0, $cart->getShipping());
        $this->assertSame(150.0, $cart->getDiscount());

        // Total: 1000 - 150 + 100 + 50 = 1000.00
        $this->assertSame(1000.0, $cart->total());
    }

    public function testMultipleInstances(): void
    {
        $default = cart('default');
        $wishlist = cart('wishlist');
        $wishlist->setDriver('memory');
        $wishlist->clear();

        $default->add(1, 'Cart Item', 100.0, 1);
        $wishlist->add(2, 'Wishlist Item', 500.0, 1);

        $this->assertSame(1, $default->count());
        $this->assertSame(1, $wishlist->count());
        $this->assertSame(100.0, $default->subtotal());
        $this->assertSame(500.0, $wishlist->subtotal());
    }

    public function testRemoveAndClear(): void
    {
        $cart = cart();
        $item = $cart->add(99, 'Monitor', 15000.0, 1);

        $this->assertTrue($cart->has($item->rowId()));
        $cart->remove($item->rowId());
        $this->assertFalse($cart->has($item->rowId()));

        $cart->add(100, 'Mouse', 800.0, 2);
        $this->assertFalse($cart->isEmpty());

        $cart->clear();
        $this->assertTrue($cart->isEmpty());
        $this->assertSame(0.0, $cart->total());
    }

    public function testExceptionOnNonexistentUpdate(): void
    {
        $this->assertThrows(CartException::class, function () {
            cart()->update('invalid_row_id_999', 5);
        });
    }
}
