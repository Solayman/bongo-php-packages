# Bongo Wishlist Package

A zero-dependency customer wishlist management package for the Bongo PHP framework.

## Features
- **Model & Product Agnostic**: Add any model (`Product`, `Course`, `Book`, etc.) or custom item types.
- **Multiple Named Wishlists**: Create distinct lists per user (e.g. `'default'`, `'holiday'`, `'saved_for_later'`).
- **Guest Session Support & Merging**: Seamlessly collect items for guests in session and merge them when the user logs in.
- **Shareable Public Links**: Generate unique public tokens and shareable URLs with toggleable privacy.
- **In-Memory Testing**: `Wishlist::fake()` enables instant in-memory testing without database dependencies.
- **User Trait & Global Helpers**: `HasWishlist` trait on `User` model, plus `wishlist()`, `wishlist_has()`, and `wishlist_count()` helpers.

## Installation & Configuration
Publish configuration and run migrations:
```bash
php bongo migrate
```

Configuration in `config/wishlist.php`:
```php
return [
    'table' => 'wishlists',
    'items_table' => 'wishlist_items',
    'default_name' => 'default',
    'share_url_prefix' => '/wishlist/shared/',
    'auto_merge_on_login' => true,
];
```

## Basic Usage

```php
use Bongo\Packages\Wishlist\Wishlist;

// Add item for user
$wishlist = Wishlist::forUser($user);
$wishlist->add($product, quantity: 1, options: ['color' => 'Navy']);

// Check and count
$wishlist->has($product); // true
$wishlist->count(); // 1

// Multi-list
$holidayList = Wishlist::forUser($user, 'holiday');
$holidayList->add($product2);

// Public sharing
$wishlist->makePublic();
$url = $wishlist->getShareUrl(); // e.g. /wishlist/shared/c7b2...

// Guest merge upon login
Wishlist::mergeGuestToUser($sessionId, $user->id);
```
