<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = function_exists('config') ? config('newsletter.table', 'newsletter_subscribers') : 'newsletter_subscribers';

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('name', 100)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('token', 64)->nullable()->index();
            $table->string('unsubscribe_token', 64)->nullable()->unique();
            $table->string('source', 50)->nullable()->default('website');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $table = function_exists('config') ? config('newsletter.table', 'newsletter_subscribers') : 'newsletter_subscribers';

        Schema::dropIfExists($table);
    }
};
