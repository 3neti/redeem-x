<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('coordinates');
            $table->unsignedInteger('radius')->default(0);
            $table->boolean('is_default')->default(false);
            $table->nullableMorphs('model');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_presets');
    }
};
