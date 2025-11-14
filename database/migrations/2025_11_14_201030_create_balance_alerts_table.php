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
        Schema::create('balance_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->index();
            $table->string('gateway')->default('netbank');
            $table->bigInteger('threshold'); // Alert when balance below this (centavos)
            $table->string('alert_type'); // email, sms, webhook
            $table->json('recipients'); // emails, phone numbers, webhook URLs
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_alerts');
    }
};
