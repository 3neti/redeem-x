<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For each user with a mobile channel entry, copy the latest value
        // to users.mobile in E.164 format (+639173011987).
        // Channels stores without '+' (639173011987); we normalize here.
        DB::table('channels')
            ->where('name', 'mobile')
            ->where('model_type', 'App\\Models\\User')
            ->orderByDesc('id')
            ->get()
            ->groupBy('model_id')
            ->each(function ($channels, $userId) {
                $latestValue = $channels->first()->value;

                try {
                    $e164 = phone($latestValue, 'PH')->formatE164();
                } catch (\Throwable) {
                    return; // Skip unparseable numbers
                }

                DB::table('users')
                    ->where('id', $userId)
                    ->whereNull('mobile')
                    ->update(['mobile' => $e164]);
            });
    }

    public function down(): void
    {
        // Clear mobile for WorkOS-sourced users (data can be re-derived from channels)
        DB::table('users')
            ->where('auth_source', 'workos')
            ->update(['mobile' => null]);
    }
};
