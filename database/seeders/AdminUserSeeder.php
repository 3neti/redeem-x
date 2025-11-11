<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@redeem.test'],
            [
                'name' => 'Admin User',
                'workos_id' => 'user_01K9H6FQS9S11T5S4MM55KA72S', // Adjust based on your auth setup
                'avatar' => 'users/default.png',
            ]
        );

        // Assign super-admin role (ensure RolePermissionSeeder has run first)
        if (!$admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }

        $this->command->info("Admin user created: {$admin->email}");
        $this->command->info("Use /dev-login/admin@redeem.test in local environment");
    }
}
