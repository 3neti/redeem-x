<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 16)->unique();
            $table->unsignedBigInteger('owner_user_id');
            $table->string('status', 32)->default('active'); // active, reserved, pending, disputed, revoked
            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('reserved_until')->nullable();
            $table->string('reservation_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_user_id', 'status']);
            $table->index('alias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_aliases');
    }
};
