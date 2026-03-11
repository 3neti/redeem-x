<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('mobile')->where('mobile', '!=', '')->orderBy('id')->each(function ($user) {
            try {
                $e164 = phone($user->mobile, 'PH')->formatE164(); // +639173011987

                if ($e164 !== $user->mobile) {
                    DB::table('users')->where('id', $user->id)->update(['mobile' => $e164]);
                }
            } catch (\Throwable) {
                // Skip rows that can't be parsed — leave as-is
            }
        });
    }

    public function down(): void
    {
        // Convert E.164 back to national format
        DB::table('users')->whereNotNull('mobile')->where('mobile', 'LIKE', '+63%')->orderBy('id')->each(function ($user) {
            try {
                $national = phone($user->mobile, 'PH')->formatForMobileDialingInCountry('PH');

                DB::table('users')->where('id', $user->id)->update(['mobile' => $national]);
            } catch (\Throwable) {
                // Skip
            }
        });
    }
};
