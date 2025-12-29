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
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_secret', 64)->nullable()->after('rate_limit_tier')
                ->comment('HMAC-SHA256 signing secret for request authentication');
            $table->boolean('signature_enabled')->default(false)->after('signature_secret')
                ->comment('Whether request signature verification is required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['signature_secret', 'signature_enabled']);
        });
    }
};
