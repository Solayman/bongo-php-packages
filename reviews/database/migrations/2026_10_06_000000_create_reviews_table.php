<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $reviewsTable = function_exists('config') ? config('reviews.table', 'reviews') : 'reviews';
        $votesTable = function_exists('config') ? config('reviews.votes_table', 'review_votes') : 'review_votes';

        Schema::create($reviewsTable, function (Blueprint $table) {
            $table->id();
            $table->string('reviewable_type', 255)->index();
            $table->string('reviewable_id', 100)->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('author_name', 100)->nullable();
            $table->string('author_email', 150)->nullable();
            $table->integer('rating');
            $table->string('title', 255)->nullable();
            $table->text('content')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_verified_buyer')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->integer('unhelpful_count')->default(0);
            $table->timestamps();
        });

        Schema::create($votesTable, function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_helpful')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $reviewsTable = function_exists('config') ? config('reviews.table', 'reviews') : 'reviews';
        $votesTable = function_exists('config') ? config('reviews.votes_table', 'review_votes') : 'review_votes';

        Schema::dropIfExists($votesTable);
        Schema::dropIfExists($reviewsTable);
    }
};
