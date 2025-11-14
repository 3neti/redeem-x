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
        Schema::create('balance_history', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->index();
            $table->string('gateway');
            $table->bigInteger('balance'); // centavos
            $table->bigInteger('available_balance'); // centavos
            $table->string('currency', 3)->default('PHP');
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_history');
    }
};
