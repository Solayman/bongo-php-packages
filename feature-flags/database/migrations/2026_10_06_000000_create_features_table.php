<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = function_exists('config') ? config('feature-flags.table', 'features') : 'features';

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index();
            $table->string('scope', 100)->default('__global__')->index();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $table = function_exists('config') ? config('feature-flags.table', 'features') : 'features';

        Schema::dropIfExists($table);
    }
};
