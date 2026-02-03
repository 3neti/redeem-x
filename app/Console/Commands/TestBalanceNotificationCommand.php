<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\BalanceNotification;
use Illuminate\Console\Command;

class TestBalanceNotificationCommand extends Command
{
    protected $signature = 'test:balance-notification 
                            {--email=lester@hurtado.ph : Email to send notification to}
                            {--mobile=09173011987 : Mobile to send notification to}
                            {--type=user : Balance type (user or system)}';

    protected $description = 'Test BalanceNotification with real delivery';

    public function handle(): int
    {
        $email = $this->option('email');
        $mobile = $this->option('mobile');
        $type = $this->option('type');

        // Find or create user
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->info("Creating user: {$email}");
            $user = User::factory()->create([
                'email' => $email,
                'name' => 'Test User',
                'mobile' => $mobile,
            ]);
        } else {
            $this->info("Using existing user: {$email}");
        }

        // Prepare balance data based on type
        if ($type === 'system') {
            $balances = [
                'wallet' => 15000.50,
                'products' => 8500.25,
                'bank' => 45000.75,
                'bank_timestamp' => now()->format('g:i A'),
                'bank_stale' => false,
            ];
            $this->info("Testing SYSTEM balance notification");
        } else {
            $balances = [
                'wallet' => 1500.50,
            ];
            $this->info("Testing USER balance notification");
        }

        // Send notification
        $notification = new BalanceNotification(
            type: $type,
            balances: $balances
        );

        $this->info("Sending notification to {$email} / {$mobile}...");
        $user->notify($notification);

        $this->info("✓ BalanceNotification sent successfully!");
        $this->info("Check:");
        $this->info("  - Email: {$email}");
        $this->info("  - SMS: {$mobile}");
        $this->info("  - Database: notifications table");

        return self::SUCCESS;
    }
}
