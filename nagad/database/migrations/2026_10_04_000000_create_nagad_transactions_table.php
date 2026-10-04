<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nagad_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 100)->unique();
            $table->string('payment_ref_id', 100)->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('BDT');
            $table->string('status', 50)->default('INITIATED');
            $table->string('issuer_trx_id', 100)->nullable();
            $table->string('client_mobile_no', 30)->nullable();
            $table->string('challenge', 100)->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nagad_transactions');
    }
};
