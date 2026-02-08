<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\HelpNotification;
use Illuminate\Console\Command;

class TestHelpNotificationCommand extends Command
{
    protected $signature = 'test:help-notification 
                            {--email=lester@hurtado.ph : Email to send notification to}
                            {--mobile=09173011987 : Mobile to send notification to}
                            {--type=general : Help type (general or command)}';

    protected $description = 'Test HelpNotification with real delivery';

    public function handle(): int
    {
        $email = $this->option('email');
        $mobile = $this->option('mobile');
        $type = $this->option('type');

        // Find or create user
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->info("Creating user: {$email}");
            $user = User::factory()->create([
                'email' => $email,
                'name' => 'Test User',
                'mobile' => $mobile,
            ]);
        } else {
            $this->info("Using existing user: {$email}");
        }

        // Prepare help message based on type
        if ($type === 'command') {
            $message = <<<'HELP'
GENERATE - Create redeemable voucher

Syntax:
GENERATE amount [flags]
GENERATE --campaign="Name"

Examples:
GENERATE 500
GENERATE 1000 --count=5
GENERATE --campaign="Petty Cash"

Flags:
--campaign="Name" - Use template
--count=5 - Number of vouchers
--inputs=loc,sig,sel - Required fields
HELP;
            $this->info('Testing COMMAND-SPECIFIC help notification');
        } else {
            $message = <<<'MSG'
Commands:
BALANCE
GENERATE amt
PAYABLE amt
SETTLEMENT amt target

Examples:
GENERATE 500
GENERATE --campaign="Name"

Flags:
--campaign="Name"
--inputs=loc,sig,sel,kyc

HELP [cmd] for details
MSG;
            $this->info('Testing GENERAL help notification');
        }

        // Send notification
        $notification = new HelpNotification($message);

        $this->info("Sending notification to {$email} / {$mobile}...");
        $user->notify($notification);

        $this->info('✓ HelpNotification sent successfully!');
        $this->info('Check:');
        $this->info("  - SMS: {$mobile}");
        $this->info('  - Database: notifications table');

        return self::SUCCESS;
    }
}
