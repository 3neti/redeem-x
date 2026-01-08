<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Laravel\Pennant\Feature;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or update admin user
        $systemEmail = env('SYSTEM_USER_ID');
        $admin = User::updateOrCreate(
            ['email' => $systemEmail],
            [
                'name' => 'Lester B. Hurtado',
                'workos_id' => 'user_01K9V1DWFP0M2312PPCTHKPK9C',
                'avatar' => 'https://ui-avatars.com/api/?name=Lester+B+Hurtado&background=random',
            ]
        );

        // Assign super-admin role (ensure RolePermissionSeeder has run first)
        if (!$admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }

        // Create or update power user (lester@hurtado.ph)
        $user = User::updateOrCreate(
            ['email' => 'lester@hurtado.ph'],
            [
                'name' => 'Lester Hurtado',
                'workos_id' => 'user_01K9H6FQS9S11T5S4MM55KA72S',
                'avatar' => 'https://workoscdn.com/images/v1/SWYo_esN8VqHMcvV6Z1SQZ0c8cAmKIr4AT_cKrzmICA',
            ]
        );

        // Set mobile number via HasChannels magic property
        $user->mobile = '09173011987';

        // Update Cash Gift campaign with mobile (campaigns created by UserObserver before mobile was set)
        $user->load('channels'); // Ensure channels are loaded
        $cashGift = $user->campaigns()->where('name', 'Cash Gift')->first();
        if ($cashGift) {
            $instructions = $cashGift->instructions->toArray();
            $instructions['feedback']['mobile'] = $user->mobile;
            $cashGift->instructions = $instructions;
            $cashGift->save();
        }

        // Assign super-admin role (ensure RolePermissionSeeder has run first)
        if (!$user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }

        // Activate settlement-vouchers feature if configured
        // Comma-separated list of emails in SETTLEMENT_VOUCHERS_ENABLED_FOR env variable
        $settlementEnabledFor = array_filter(
            array_map('trim', explode(',', env('SETTLEMENT_VOUCHERS_ENABLED_FOR', '')))
        );
        
        if (in_array($user->email, $settlementEnabledFor)) {
            Feature::for($user)->activate('settlement-vouchers');
            $this->command->info("  ✓ Activated 'settlement-vouchers' for {$user->email}");
        }

        $this->command->info("✅ Admin user created: {$admin->email} (super-admin role)");
        $this->command->info("✅ Power user created: {$user->email} (super-admin role)");
        $this->command->info("Use /dev-login/{$admin->email} or /dev-login/{$user->email} in local environment");
    }
}
