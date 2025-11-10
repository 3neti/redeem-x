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
        Schema::create('user_voucher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_code')->index();
            $table->foreign('voucher_code')
                  ->references('code')
                  ->on('vouchers')
                  ->cascadeOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
            
            $table->unique(['user_id', 'voucher_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_voucher');
    }
};
