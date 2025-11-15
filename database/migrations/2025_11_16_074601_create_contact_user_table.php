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
        Schema::create('contact_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('relationship_type')->default('sender'); // sender, beneficiary, etc.
            $table->decimal('total_sent', 15, 2)->default(0); // Cumulative amount
            $table->integer('transaction_count')->default(0);
            $table->timestamp('first_transaction_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->json('metadata')->nullable(); // Store per-transaction details (institution, operation_id, etc.)
            $table->timestamps();
            
            $table->unique(['contact_id', 'user_id', 'relationship_type']);
            $table->index(['user_id', 'relationship_type']);
            $table->index('last_transaction_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_user');
    }
};
