<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use LBHurtado\Wallet\Actions\TopupWalletAction;

class SystemWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get system user and regular user
        $systemEmail = env('SYSTEM_USER_ID');
        $systemUser = User::where('email', $systemEmail)->first();

        if (!$systemUser) {
            $this->command->error('System user not found. Please run SystemUserSeeder first.');
            return;
        }

        // First, deposit funds into system user's wallet
        $systemUser->depositFloat(1_000_000.00); // 1 million for system wallet
        $this->command->info("✅ System wallet funded with ₱1,000,000.00");
    }
}
