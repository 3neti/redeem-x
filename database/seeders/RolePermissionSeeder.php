<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        Permission::firstOrCreate(['name' => 'manage pricing']);
        Permission::firstOrCreate(['name' => 'view balance']);
        Permission::firstOrCreate(['name' => 'view all billing']);
        Permission::firstOrCreate(['name' => 'manage users']);
        Permission::firstOrCreate(['name' => 'manage preferences']);

        // Create roles and assign permissions
        
        // Super Admin - Full access to everything
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions([
            'manage pricing',
            'view balance',
            'view all billing',
            'manage users',
            'manage preferences',
        ]);

        // Admin - Can view balance monitoring
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions([
            'view balance',
            'view all billing',
        ]);

        // Power User - Can manage pricing
        $powerUser = Role::firstOrCreate(['name' => 'power-user']);
        $powerUser->syncPermissions([
            'manage pricing',
            'view balance',
        ]);

        // Basic User - No admin permissions
        Role::firstOrCreate(['name' => 'basic-user']);
    }
}
