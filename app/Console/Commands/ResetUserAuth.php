<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Pennant\Feature;

class ResetUserAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reset-auth {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all authorization state (roles, permissions cache, feature flags) for a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: {$email}");

            return 1;
        }

        // Clear permission cache
        $this->info('🔄 Clearing permission cache...');
        $this->call('permission:cache-reset');

        // Clear all sessions (forces re-authentication)
        $this->info('🔄 Clearing all sessions...');
        \DB::table('sessions')->delete();
        $this->comment('   All users will be logged out');

        // Clear application cache
        $this->info('🔄 Clearing application cache...');
        $this->call('cache:clear');

        // Clear all Pennant feature flags for this user
        $this->info('🔄 Clearing feature flags...');
        Feature::for($user)->forget('advanced-pricing-mode');
        Feature::for($user)->forget('beta-features');

        // Recalculate feature flags
        $advancedMode = Feature::for($user)->active('advanced-pricing-mode');
        $betaFeatures = Feature::for($user)->active('beta-features');

        // Display current state
        $this->newLine();
        $this->info("✅ Authorization state reset for {$email}");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Roles', $user->roles->pluck('name')->join(', ') ?: '(none)'],
                ['Permissions', $user->getAllPermissions()->pluck('name')->join(', ') ?: '(none)'],
                ['Advanced Pricing Mode', $advancedMode ? '✓ Enabled' : '✗ Disabled'],
                ['Beta Features', $betaFeatures ? '✓ Enabled' : '✗ Disabled'],
            ]
        );

        $this->newLine();
        $this->comment('💡 Tip: Hard refresh your browser (Cmd+Shift+R) to see changes');

        return 0;
    }
}
