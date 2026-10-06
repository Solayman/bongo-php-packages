<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $wishlistsTable = function_exists('config') ? config('wishlist.table', 'wishlists') : 'wishlists';
        $itemsTable = function_exists('config') ? config('wishlist.items_table', 'wishlist_items') : 'wishlist_items';

        Schema::create($wishlistsTable, function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('name', 100)->default('default');
            $table->string('share_token', 64)->nullable()->unique();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create($itemsTable, function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->index();
            $table->string('item_type', 255);
            $table->string('item_id', 100);
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->text('options')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $wishlistsTable = function_exists('config') ? config('wishlist.table', 'wishlists') : 'wishlists';
        $itemsTable = function_exists('config') ? config('wishlist.items_table', 'wishlist_items') : 'wishlist_items';

        Schema::dropIfExists($itemsTable);
        Schema::dropIfExists($wishlistsTable);
    }
};
