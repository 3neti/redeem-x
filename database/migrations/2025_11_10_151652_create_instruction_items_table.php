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
        Schema::create('instruction_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('index')->unique();
            $table->string('type');
            $table->integer('price')->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instruction_items');
    }
};
