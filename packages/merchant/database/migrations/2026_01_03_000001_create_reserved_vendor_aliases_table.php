<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserved_vendor_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 16)->unique();
            $table->text('reason');
            $table->unsignedBigInteger('reserved_by')->nullable();
            $table->timestamp('reserved_at');
            $table->timestamps();
            
            $table->index('alias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_vendor_aliases');
    }
};
