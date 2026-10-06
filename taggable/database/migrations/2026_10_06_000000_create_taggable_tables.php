<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tagsTable = function_exists('config') ? config('taggable.tags_table', 'tags') : 'tags';
        $taggablesTable = function_exists('config') ? config('taggable.taggables_table', 'taggables') : 'taggables';

        Schema::create($tagsTable, function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index();
            $table->string('slug', 120)->unique();
            $table->string('type', 50)->nullable()->index();
            $table->text('description')->nullable();
            $table->integer('count')->default(0)->index();
            $table->timestamps();
        });

        Schema::create($taggablesTable, function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->index();
            $table->string('taggable_type', 255);
            $table->string('taggable_id', 100);
            $table->timestamps();

            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        $tagsTable = function_exists('config') ? config('taggable.tags_table', 'tags') : 'tags';
        $taggablesTable = function_exists('config') ? config('taggable.taggables_table', 'taggables') : 'taggables';

        Schema::dropIfExists($taggablesTable);
        Schema::dropIfExists($tagsTable);
    }
};
