<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Location Presets API Routes
|--------------------------------------------------------------------------
|
| API endpoints for managing user location presets (geofence polygons).
| All routes require authentication.
|
*/

Route::prefix('location-presets')->name('api.location-presets.')->group(function () {
    // List all presets (user's + system defaults)
    // GET /api/v1/location-presets
    Route::get('', [\App\Actions\Api\LocationPresets\ListLocationPresets::class, 'asController'])
        ->name('index');

    // Create a new preset
    // POST /api/v1/location-presets
    Route::post('', [\App\Actions\Api\LocationPresets\CreateLocationPreset::class, 'asController'])
        ->name('store');

    // Update a preset
    // PUT /api/v1/location-presets/{id}
    Route::put('{id}', [\App\Actions\Api\LocationPresets\UpdateLocationPreset::class, 'asController'])
        ->name('update');

    // Delete a preset
    // DELETE /api/v1/location-presets/{id}
    Route::delete('{id}', [\App\Actions\Api\LocationPresets\DeleteLocationPreset::class, 'asController'])
        ->name('destroy');
});
