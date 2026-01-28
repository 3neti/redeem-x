<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSmsBalance extends Command
{
    protected $signature = 'test:sms-balance 
                            {mobile? : Mobile number to test with}
                            {--system : Test system balance (admin)}
                            {--non-admin : Test with non-admin user}';

    protected $description = 'Test SMS BALANCE command functionality';

    public function handle(): int
    {
        $mobile = $this->argument('mobile');
        $isSystemTest = $this->option('system');
        $isNonAdminTest = $this->option('non-admin');

        // If no mobile provided, use first user or create one
        if (!$mobile) {
            $user = User::first();
            
            if (!$user) {
                $this->error('No users found. Creating test user...');
                $user = User::factory()->create([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]);
                $this->info("Created user: {$user->email}");
            }

            // Get mobile from channels
            $mobileChannel = $user->channels()->where('name', 'mobile')->first();
            
            if (!$mobileChannel) {
                $this->error('User has no mobile channel. Creating one...');
                $user->channels()->create([
                    'name' => 'mobile',
                    'value' => '09173011987',
                ]);
                $mobile = '09173011987';
            } else {
                $mobile = $mobileChannel->value;
            }

            $this->info("Using mobile: {$mobile}");
            $this->info("User: {$user->name} ({$user->email})");
            
            // Check permissions
            if ($user->can('view-balances')) {
                $this->info("✓ User has 'view-balances' permission (admin)");
            } else {
                $this->warn("✗ User does NOT have 'view-balances' permission (regular user)");
            }

            // Show wallet balance
            $balance = $user->balanceFloat;
            $this->info("Wallet balance: ₱" . number_format($balance, 2));
            
            $this->newLine();
        }

        // Build SMS text
        if ($isSystemTest) {
            $smsText = 'BALANCE --system';
            $this->info('Testing: BALANCE --system (admin system balance)');
        } else {
            $smsText = 'BALANCE';
            $this->info('Testing: BALANCE (user balance)');
        }

        // If testing with non-admin, create a non-admin user
        if ($isNonAdminTest) {
            $this->info('Creating non-admin user for permission test...');
            $nonAdminUser = User::factory()->create([
                'name' => 'Regular User',
                'email' => 'regular@example.com',
            ]);
            
            // Create mobile channel
            $nonAdminUser->channels()->create([
                'name' => 'mobile',
                'value' => '09171234567',
            ]);
            
            $mobile = '09171234567';
            $this->info("Created non-admin user: {$nonAdminUser->email}");
            $this->info("Mobile: {$mobile}");
            $this->newLine();
        }

        // Simulate SMS via internal route
        $this->info('Sending SMS to internal route...');
        $this->info("From: {$mobile}");
        $this->info("Message: {$smsText}");
        $this->newLine();

        try {
            // Use the internal SMS route
            $response = Http::post(config('app.url') . '/sms', [
                'from' => $mobile,
                'to' => config('sms.default_sender', '2929'), // Short code
                'message' => $smsText,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $this->info('✓ SMS processed successfully');
                $this->newLine();
                
                $this->info('Response:');
                $this->line('─────────────────────────────────────────────');
                $this->line($data['message'] ?? 'No message in response');
                $this->line('─────────────────────────────────────────────');
                $this->newLine();
                
                // Show full response data
                if ($this->output->isVerbose()) {
                    $this->info('Full response data:');
                    dump($data);
                }
                
                return self::SUCCESS;
            } else {
                $this->error('✗ SMS processing failed');
                $this->error("Status: {$response->status()}");
                $this->error("Response: {$response->body()}");
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception occurred:');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
