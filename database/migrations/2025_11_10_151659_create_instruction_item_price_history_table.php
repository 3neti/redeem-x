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
        Schema::create('instruction_item_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instruction_item_id')->constrained()->cascadeOnDelete();
            $table->integer('old_price');
            $table->integer('new_price');
            $table->string('currency', 3)->default('PHP');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('effective_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instruction_item_price_history');
    }
};
