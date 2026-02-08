<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class TestTopUpCommand extends Command
{
    protected $signature = 'test:topup 
                            {amount=500 : Amount to top-up in PHP} 
                            {--user= : User ID or email} 
                            {--institution= : Institution code (GCASH, MAYA, etc.)}
                            {--simulate : Automatically simulate payment}';

    protected $description = 'Test top-up flow end-to-end';

    public function handle()
    {
        $this->info('🚀 Testing Top-Up Flow');
        $this->newLine();

        // Get or create user
        $user = $this->getUser();
        $this->info("👤 User: {$user->name} ({$user->email})");

        $initialBalance = $user->balanceFloat;
        $this->info('💰 Initial Balance: ₱'.number_format($initialBalance, 2));
        $this->newLine();

        // Get amount
        $amount = (float) $this->argument('amount');
        $institution = $this->option('institution');

        try {
            // Step 1: Initiate top-up
            $this->info("📝 Step 1: Initiating top-up of ₱{$amount}...");
            $result = $user->initiateTopUp(
                amount: $amount,
                gateway: 'netbank',
                institutionCode: $institution
            );

            $this->info('✓ Top-up initiated successfully');
            $this->info("   Reference: {$result->reference_no}");
            $this->info("   Redirect URL: {$result->redirect_url}");
            $this->newLine();

            // Step 2: Check database
            $this->info('📊 Step 2: Checking database...');
            $topUp = $user->getTopUpByReference($result->reference_no);
            $this->info('✓ Top-up record found');
            $this->info("   Status: {$topUp->getStatus()}");
            $this->info('   Amount: ₱'.number_format($topUp->getAmount(), 2));
            $this->newLine();

            // Step 3: Simulate payment (if option set or in fake mode)
            if ($this->option('simulate') || config('payment-gateway.netbank.direct_checkout.use_fake')) {
                $this->info('💳 Step 3: Simulating payment...');
                $topUp->markAsPaid('TEST-'.time());
                $this->info('✓ Payment marked as PAID');
                $this->newLine();

                // Step 4: Credit wallet
                $this->info('💸 Step 4: Crediting wallet...');
                $user->creditWalletFromTopUp($topUp);
                $user->refresh();

                $newBalance = $user->balanceFloat;
                $credited = $newBalance - $initialBalance;

                $this->info('✓ Wallet credited successfully');
                $this->info('   Previous Balance: ₱'.number_format($initialBalance, 2));
                $this->info('   New Balance: ₱'.number_format($newBalance, 2));
                $this->info('   Credited: ₱'.number_format($credited, 2));
                $this->newLine();

                if (abs($credited - $amount) < 0.01) {
                    $this->info('✅ SUCCESS: Amount credited matches top-up amount!');
                } else {
                    $this->error("❌ ERROR: Amount mismatch! Expected ₱{$amount}, got ₱{$credited}");

                    return 1;
                }
            } else {
                $this->warn('⚠️  Payment not simulated. Visit the redirect URL to complete payment.');
            }

            // Summary
            $this->newLine();
            $this->info('📋 Summary:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Reference No', $result->reference_no],
                    ['Amount', '₱'.number_format($amount, 2)],
                    ['Gateway', $result->gateway],
                    ['Institution', $institution ?: 'Any'],
                    ['Initial Balance', '₱'.number_format($initialBalance, 2)],
                    ['Final Balance', '₱'.number_format($user->fresh()->balanceFloat, 2)],
                    ['Status', $topUp->fresh()->getStatus()],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return 1;
        }
    }

    protected function getUser(): User
    {
        $userOption = $this->option('user');

        if ($userOption) {
            if (filter_var($userOption, FILTER_VALIDATE_EMAIL)) {
                return User::where('email', $userOption)->firstOrFail();
            }

            return User::findOrFail($userOption);
        }

        return User::first() ?? User::factory()->create();
    }
}
