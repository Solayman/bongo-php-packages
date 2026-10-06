<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Wishlist Table Names
    |--------------------------------------------------------------------------
    |
    | Database table names used for storing wishlists and wishlist items.
    |
    */
    'table' => 'wishlists',
    'items_table' => 'wishlist_items',

    /*
    |--------------------------------------------------------------------------
    | Default List Name
    |--------------------------------------------------------------------------
    |
    | The default list name assigned to newly saved items (e.g. 'default', 'main').
    |
    */
    'default_name' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Shareable URL Base Path
    |--------------------------------------------------------------------------
    |
    | Base path prefix for public wishlist links (e.g. /wishlist/shared/{token}).
    |
    */
    'share_url_prefix' => '/wishlist/shared/',

    /*
    |--------------------------------------------------------------------------
    | Automatic Guest Merge on Login
    |--------------------------------------------------------------------------
    |
    | When true, items added while browsing as a guest are merged into the
    | user's account upon authentication.
    |
    */
    'auto_merge_on_login' => true,
];
