<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Settings API Routes
|--------------------------------------------------------------------------
|
| API endpoints for user settings management.
| All routes require authentication.
|
*/

Route::prefix('settings')->name('api.settings.')->group(function () {
    // Profile settings
    Route::prefix('profile')->name('profile.')->group(function () {
        // Get profile settings
        // GET /api/v1/settings/profile
        Route::get('', [\App\Actions\Api\Settings\GetProfile::class, 'asController'])
            ->name('show');

        // Update profile settings
        // PATCH /api/v1/settings/profile
        Route::patch('', [\App\Actions\Api\Settings\UpdateProfile::class, 'asController'])
            ->name('update');
    });

    // Wallet configuration
    Route::prefix('wallet')->name('wallet.')->group(function () {
        // Get wallet configuration
        // GET /api/v1/settings/wallet
        Route::get('', [\App\Actions\Api\Settings\GetWalletConfig::class, 'asController'])
            ->name('show');

        // Update wallet configuration
        // PATCH /api/v1/settings/wallet
        Route::patch('', [\App\Actions\Api\Settings\UpdateWalletConfig::class, 'asController'])
            ->name('update');
    });

    // User preferences
    Route::prefix('preferences')->name('preferences.')->group(function () {
        // Get preferences
        // GET /api/v1/settings/preferences
        Route::get('', [\App\Actions\Api\Settings\GetPreferences::class, 'asController'])
            ->name('show');

        // Update preferences
        // PATCH /api/v1/settings/preferences
        Route::patch('', [\App\Actions\Api\Settings\UpdatePreferences::class, 'asController'])
            ->name('update');
    });
});
