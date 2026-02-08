<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Pennant\Feature;

class ListUserFeatures extends Command
{
    protected $signature = 'feature:list {email : User email address}';

    protected $description = 'List all feature flags for a user';

    public function handle(): int
    {
        $email = $this->argument('email');

        // Find user
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        // Define all features to check
        $features = [
            'settlement-vouchers' => 'Settlement Vouchers',
            'advanced-pricing-mode' => 'Advanced Pricing Mode',
            'beta-features' => 'Beta Features',
        ];

        $this->info("Feature flags for: {$email}");
        $this->newLine();

        $rows = [];
        foreach ($features as $featureKey => $featureName) {
            $isActive = Feature::for($user)->active($featureKey);
            $status = $isActive ? '<fg=green>✓ ENABLED</>' : '<fg=red>✗ DISABLED</>';

            $rows[] = [$featureName, $featureKey, $status];
        }

        $this->table(
            ['Feature', 'Key', 'Status'],
            $rows
        );

        $this->newLine();
        $this->line('To manage features, use:');
        $this->line("  <fg=yellow>php artisan feature:manage {feature-key} {$email} --enable</>");
        $this->line("  <fg=yellow>php artisan feature:manage {feature-key} {$email} --disable</>");

        return self::SUCCESS;
    }
}
