<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = function_exists('config') ? config('settings.table', 'settings') : 'settings';

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('setting_group')->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $table = function_exists('config') ? config('settings.table', 'settings') : 'settings';

        Schema::dropIfExists($table);
    }
};
