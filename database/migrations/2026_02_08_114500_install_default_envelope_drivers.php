<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Install default envelope drivers from package stubs to storage.
     * This ensures drivers are available on fresh installations.
     *
     * To add new default drivers later:
     * 1. Add YAML files to packages/settlement-envelope/resources/stubs/drivers/
     * 2. Run: php artisan envelope:install-drivers
     */
    public function up(): void
    {
        // Create storage directory if needed
        $path = storage_path('app/envelope-drivers');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // Install default drivers from package stubs
        Artisan::call('envelope:install-drivers');
    }

    /**
     * Reverse the migrations.
     *
     * Note: This does NOT remove drivers to preserve user customizations.
     * To remove all drivers, manually delete storage/app/envelope-drivers/
     */
    public function down(): void
    {
        // Intentionally empty - don't delete user's drivers on rollback
    }
};
