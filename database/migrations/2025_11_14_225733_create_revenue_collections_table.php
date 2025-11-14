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
        Schema::create('revenue_collections', function (Blueprint $table) {
            $table->id();
            
            // Source: InstructionItem
            $table->foreignId('instruction_item_id')->constrained()->cascadeOnDelete();
            
            // Collected by (admin/system user)
            $table->foreignId('collected_by_user_id')->constrained('users');
            
            // Destination: Polymorphic (User, Organization, etc.)
            $table->morphs('destination');
            
            // Amount collected (in centavos)
            $table->bigInteger('amount');
            
            // Transfer reference
            $table->uuid('transfer_uuid');
            $table->foreign('transfer_uuid')->references('uuid')->on('transfers');
            
            // Optional notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('collected_by_user_id');
            $table->index('transfer_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_collections');
    }
};
