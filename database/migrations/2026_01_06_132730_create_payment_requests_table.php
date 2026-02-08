<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique()->comment('QR payment reference ID');
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->comment('Amount in minor units (cents)');
            $table->string('currency', 3)->default('PHP');
            $table->json('payer_info')->nullable()->comment('Optional payer details');
            $table->enum('status', ['pending', 'awaiting_confirmation', 'confirmed', 'expired'])->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['voucher_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
