<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class LoginAsUser extends Command
{
    protected $signature = 'login:as {email}';

    protected $description = 'Generate a login URL for a user (development only)';

    public function handle()
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found!');

            return 1;
        }

        $this->info('Test user found:');
        $this->info("Email: {$user->email}");
        $this->info("Name: {$user->name}");
        $this->info('Wallet: ₱'.number_format($user->balanceFloatNum, 2));
        $this->newLine();

        $this->warn('Since this app uses WorkOS authentication, you need to:');
        $this->info('1. Go to your WorkOS dashboard');
        $this->info('2. Add http://redeem-x.test/authenticate to the redirect URIs');
        $this->newLine();

        $this->info('OR for development testing, I can create a test route...');

        return 0;
    }
}
