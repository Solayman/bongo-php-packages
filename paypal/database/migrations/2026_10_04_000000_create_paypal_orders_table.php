<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paypal_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 150)->unique();
            $table->string('capture_id', 150)->nullable()->index();
            $table->string('status', 50)->default('CREATED');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('payer_email', 150)->nullable();
            $table->string('payer_id', 150)->nullable();
            $table->string('payer_name', 150)->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_orders');
    }
};
