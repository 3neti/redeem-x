<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Pennant\Feature;

class ManageUserFeatures extends Command
{
    protected $signature = 'feature:manage
                            {feature : The feature name (e.g., settlement-vouchers)}
                            {email : User email address}
                            {--enable : Enable the feature}
                            {--disable : Disable the feature}
                            {--status : Check feature status}';

    protected $description = 'Manage feature flags for individual users';

    public function handle(): int
    {
        $featureName = $this->argument('feature');
        $email = $this->argument('email');

        // Find user
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        // Check status
        if ($this->option('status')) {
            $isActive = Feature::for($user)->active($featureName);
            $status = $isActive ? '<fg=green>ENABLED</>' : '<fg=red>DISABLED</>';
            $this->info("Feature '{$featureName}' for {$email}: {$status}");

            return self::SUCCESS;
        }

        // Enable feature
        if ($this->option('enable')) {
            Feature::for($user)->activate($featureName);
            $this->info("✓ Enabled '{$featureName}' for {$email}");

            // Verify
            if (Feature::for($user)->active($featureName)) {
                $this->line('  Verified: Feature is now active');
            } else {
                $this->warn('  Warning: Feature may not be active due to default resolver');
            }

            return self::SUCCESS;
        }

        // Disable feature
        if ($this->option('disable')) {
            Feature::for($user)->deactivate($featureName);
            $this->info("✓ Disabled '{$featureName}' for {$email}");

            // Verify
            if (! Feature::for($user)->active($featureName)) {
                $this->line('  Verified: Feature is now inactive');
            } else {
                $this->warn('  Warning: Feature is still active due to environment/role defaults');
                $this->line('  Check AppServiceProvider feature definition for defaults');
            }

            return self::SUCCESS;
        }

        $this->error('Please specify --enable, --disable, or --status');

        return self::FAILURE;
    }
}
