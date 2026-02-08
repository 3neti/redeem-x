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
        Schema::create('voucher_generation_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->json('voucher_codes');
            $table->integer('voucher_count');
            $table->json('instructions_snapshot');
            $table->json('charge_breakdown');
            $table->decimal('total_charge', 10, 2);
            $table->decimal('charge_per_voucher', 10, 2);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['user_id', 'generated_at']);
            $table->index('campaign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_generation_charges');
    }
};
