<?php

declare(strict_types=1);

use App\Models\Campaign;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
| Authentication: Laravel Sanctum
| - SPA authentication (session-based for web UI)
| - Token authentication (for mobile apps and third-party)
|
*/

/**
 * Public API routes (no authentication required).
 */
Route::prefix('v1')->group(function () {
    // Public redemption API routes
    // Rate limited to 10 requests per minute for public access
    Route::middleware(['throttle:10,1'])->group(base_path('routes/api/redemption.php'));
});

/**
 * Authenticated API routes.
 * Requires Laravel Sanctum authentication.
 */
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->group(function () {
        // Voucher management API
        require base_path('routes/api/vouchers.php');

        // Transaction history API
        require base_path('routes/api/transactions.php');

        // Settings API
        require base_path('routes/api/settings.php');

        // Contact management API
        require base_path('routes/api/contacts.php');

        // Campaigns API (for Generate Vouchers dropdown)
        Route::get('/campaigns', function (\Illuminate\Http\Request $request) {
            return Campaign::where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->select('id', 'name', 'slug', 'instructions')
                ->get();
        });

        Route::get('/campaigns/{campaign}', function (Campaign $campaign) {
            Gate::authorize('view', $campaign);
            return $campaign;
        });
    });

/**
 * Webhook API routes (public but with signature verification).
 */
Route::prefix('v1/webhooks')
    ->middleware(['throttle:30,1'])
    ->group(base_path('routes/api/webhooks.php'));
