<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sslcommerz_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tran_id', 100)->unique();
            $table->string('val_id', 100)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('BDT');
            $table->string('status', 30)->default('pending');
            $table->string('card_type', 50)->nullable();
            $table->string('bank_tran_id', 100)->nullable();
            $table->string('card_brand', 50)->nullable();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_email', 150)->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sslcommerz_transactions');
    }
};
