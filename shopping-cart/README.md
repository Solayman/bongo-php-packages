# Bongo Shopping Cart Package

A fluent, zero-dependency shopping cart management engine for Bongo PHP with support for multiple cart instances, custom item options, taxes, shipping, and promotional discounts.

## Features
- **Zero External Dependencies**: Pure PHP 8.0+.
- **Multi-Instance Support**: Easily manage `default` cart, `wishlist`, or `compare` side by side.
- **Custom Item Options**: Support variations like size, color, SKU.
- **Comprehensive Math**: Item subtotals, configurable per-item or global tax rates, shipping fees, and discounts.
- **Coupon Integration**: Seamlessly bridges with the official `coupon` package.
- **Session & Memory Drivers**: Automatic session persistence in HTTP requests with fallback to memory driver in CLI/tests.

## Installation
```bash
php bongo package:add shopping-cart
```

## Basic Usage

```php
use Bongo\Packages\ShoppingCart\Cart;

// Add item
$item = cart()->add(id: 101, name: 'Cotton T-Shirt', price: 500.0, quantity: 2, options: ['size' => 'L', 'color' => 'Navy']);

// Cart counts
cart()->count();     // 2 (total quantity)
cart()->countRows(); // 1 (distinct items)

// Subtotal & Totals
cart()->subtotal(); // 1000.00
cart()->setShipping(60.0);
cart()->setTaxRate(5.0); // 5% tax
cart()->total(); // 1000 - 0 + 50 + 60 = 1110.00

// Update item quantity
cart()->update($item->rowId(), 3);

// Apply discount
cart()->setDiscount(100.0);

// Clear cart
cart()->clear();
```

## Multi-Instance
```php
$wishlist = cart('wishlist');
$wishlist->add(202, 'Wireless Headphones', 3500.0);
```

## CLI Commands
```bash
php bongo cart:clear
php bongo cart:clear wishlist
```
