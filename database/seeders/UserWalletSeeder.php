<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use LBHurtado\Wallet\Actions\TopupWalletAction;

class UserWalletSeeder extends Seeder
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

        $user = User::where('email', '!=', $systemEmail)->first();

        if (!$user) {
            $this->command->error('No regular users found. Please create a user first.');
            return;
        }

        // Now transfer from system to user
        $systemUser->transferFloat($user, 100_000.00);
        $this->command->info("✅ Transferred ₱100,000.00 from system to {$user->email}");

        // Refresh the wallet balance
        $user->wallet->refreshBalance();

        $this->command->info("✅ Funded {$user->email}'s wallet with ₱100,000.00");
        $this->command->info("Current balance: ₱" . number_format($user->wallet->balanceFloat, 2));
    }
}
