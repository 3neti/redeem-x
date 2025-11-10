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
        Permission::create(['name' => 'manage pricing']);
        Permission::create(['name' => 'view all billing']);
        Permission::create(['name' => 'manage users']);

        // Create roles
        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo([
            'manage pricing',
            'view all billing',
            'manage users',
        ]);
    }
}
