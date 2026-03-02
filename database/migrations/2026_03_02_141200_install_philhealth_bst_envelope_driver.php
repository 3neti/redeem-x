<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Sync new envelope driver stubs to storage.
     *
     * The philhealth-bst driver stub was added after the initial
     * install_default_envelope_drivers migration ran. Without --force,
     * only NEW stubs are installed (existing drivers are preserved).
     */
    public function up(): void
    {
        $path = storage_path('app/envelope-drivers');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        Artisan::call('envelope:install-drivers');
    }

    public function down(): void
    {
        // Intentionally empty - don't delete drivers on rollback
    }
};
