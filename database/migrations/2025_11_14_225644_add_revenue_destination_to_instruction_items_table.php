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
        Schema::table('instruction_items', function (Blueprint $table) {
            // Polymorphic relationship for flexible revenue destination
            $table->string('revenue_destination_type')->nullable()->after('meta');
            $table->unsignedBigInteger('revenue_destination_id')->nullable()->after('revenue_destination_type');
            
            // Index for polymorphic lookup
            $table->index(['revenue_destination_type', 'revenue_destination_id'], 'instruction_items_revenue_destination_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instruction_items', function (Blueprint $table) {
            $table->dropIndex('instruction_items_revenue_destination_index');
            $table->dropColumn(['revenue_destination_type', 'revenue_destination_id']);
        });
    }
};
