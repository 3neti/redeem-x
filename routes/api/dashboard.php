<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard API Routes
|--------------------------------------------------------------------------
|
| API endpoints for dashboard statistics and recent activity.
| All routes require authentication.
|
*/

Route::prefix('dashboard')->name('api.dashboard.')->group(function () {
    // Get dashboard statistics
    // GET /api/v1/dashboard/stats
    Route::get('stats', [\App\Actions\Api\Dashboard\GetDashboardStats::class, 'asController'])
        ->name('stats');

    // Get recent activity
    // GET /api/v1/dashboard/activity
    Route::get('activity', [\App\Actions\Api\Dashboard\GetRecentActivity::class, 'asController'])
        ->name('activity');
});
