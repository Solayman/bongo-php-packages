<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = function_exists('config') ? config('activitylog.table', 'activity_log') : 'activity_log';

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->default('default');
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();
            $table->text('properties')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $table = function_exists('config') ? config('activitylog.table', 'activity_log') : 'activity_log';

        Schema::dropIfExists($table);
    }
};
