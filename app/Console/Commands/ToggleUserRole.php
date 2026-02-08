<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ToggleUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:toggle-role {email} {role=super-admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Toggle a role for a user (add if missing, remove if present)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $role = $this->argument('role');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: {$email}");

            return 1;
        }

        if ($user->hasRole($role)) {
            $user->removeRole($role);
            $this->info("✅ Removed '{$role}' role from {$email}");
            $this->line('Current roles: '.($user->roles->pluck('name')->join(', ') ?: '(none)'));
        } else {
            $user->assignRole($role);
            $this->info("✅ Added '{$role}' role to {$email}");
            $this->line('Current roles: '.$user->roles->pluck('name')->join(', '));
        }

        return 0;
    }
}
