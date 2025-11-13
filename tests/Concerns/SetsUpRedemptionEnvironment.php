<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

trait SetsUpRedemptionEnvironment
{
    protected User $systemUser;

    /**
     * Set up the redemption test environment.
     * 
     * This method:
     * - Seeds the database with roles, permissions, users, and instruction items
     * - Mocks external API calls (funds API, etc.)
     * - Returns the system user for convenience
     */
    protected function setUpRedemptionEnvironment(): User
    {
        // Seed the database with all necessary data
        $this->seed([
            \Database\Seeders\RolePermissionSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\SystemWalletSeeder::class,
            \Database\Seeders\InstructionItemSeeder::class,
        ]);

        // Mock external API responses
        Http::fake([
            config('services.funds_api.endpoint') => Http::response([
                'available' => true,
            ], 200),
        ]);

        // Get system user for tests
        $this->systemUser = User::where('email', env('SYSTEM_USER_ID'))->firstOrFail();

        return $this->systemUser;
    }

    /**
     * Get the system user (must call setUpRedemptionEnvironment first).
     */
    protected function getSystemUser(): User
    {
        return $this->systemUser;
    }
}
