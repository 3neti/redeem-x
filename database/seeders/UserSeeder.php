<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

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

        // Create or update ordinary user
        $user = User::updateOrCreate(
            ['email' => 'lester@hurtado.ph'],
            [
                'name' => 'Lester Hurtado',
                'workos_id' => 'user_01K9H6FQS9S11T5S4MM55KA72S',
                'avatar' => 'https://workoscdn.com/images/v1/SWYo_esN8VqHMcvV6Z1SQZ0c8cAmKIr4AT_cKrzmICA',
            ]
        );

        $this->command->info("✅ Admin user created: {$admin->email}");
        $this->command->info("✅ Ordinary user created: {$user->email}");
        $this->command->info("Use /dev-login/{$admin->email} or /dev-login/{$user->email} in local environment");
    }
}
