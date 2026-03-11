<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email_verified_at');
            $table->string('mobile')->nullable()->index()->after('password');
            $table->string('auth_source')->default('local')->after('mobile');
            $table->string('status')->default('active')->after('auth_source');
            $table->timestamp('mobile_verified_at')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('mobile_verified_at');
        });

        // Set auth_source to 'workos' for existing users (they all have workos_id)
        DB::table('users')->whereNotNull('workos_id')->update(['auth_source' => 'workos']);

        // Make workos_id nullable — the unique index already exists from the original migration
        Schema::table('users', function (Blueprint $table) {
            $table->string('workos_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'mobile',
                'auth_source',
                'status',
                'mobile_verified_at',
                'last_login_at',
            ]);
        });

        // Restore workos_id as NOT NULL (only safe if all rows have values)
        Schema::table('users', function (Blueprint $table) {
            $table->string('workos_id')->nullable(false)->unique()->change();
        });
    }
};
