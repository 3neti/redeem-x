<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->text('description')->nullable();
            $table->string('merchant_category_code', 4)->default('0000'); // MCC for QR Ph
            $table->string('logo_url')->nullable();
            $table->boolean('allow_tip')->default(false);
            $table->boolean('is_dynamic')->default(false); // Dynamic QR (no fixed amount)
            $table->decimal('default_amount', 10, 2)->nullable();
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_amount', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
