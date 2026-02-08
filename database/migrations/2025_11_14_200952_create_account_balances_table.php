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
        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->index();
            $table->string('gateway')->default('netbank');
            $table->bigInteger('balance')->default(0); // centavos
            $table->bigInteger('available_balance')->default(0); // centavos
            $table->string('currency', 3)->default('PHP');
            $table->timestamp('checked_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['account_number', 'gateway']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_balances');
    }
};
