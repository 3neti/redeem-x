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

        // Deposits & Senders API
        require base_path('routes/api/deposits.php');

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
        
        // Charge calculation API (for real-time pricing preview)
        Route::post('/calculate-charges', \App\Http\Controllers\Api\ChargeCalculationController::class)
            ->name('calculate-charges');

        // Balance monitoring API
        Route::prefix('balances')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\BalanceController::class, 'index']);
            Route::get('/{accountNumber}', [\App\Http\Controllers\Api\BalanceController::class, 'show']);
            Route::post('/{accountNumber}/refresh', [\App\Http\Controllers\Api\BalanceController::class, 'refresh']);
            Route::get('/{accountNumber}/history', [\App\Http\Controllers\Api\BalanceController::class, 'history']);
        });
        
        // Wallet QR code generation API
        Route::post('/wallet/generate-qr', \App\Actions\Api\Wallet\GenerateQrCode::class)
            ->name('api.wallet.generate-qr');
        
        // Merchant profile API
        Route::get('/merchant/profile', [\App\Http\Controllers\Api\MerchantProfileController::class, 'show'])
            ->name('api.merchant.profile.show');
        Route::put('/merchant/profile', [\App\Http\Controllers\Api\MerchantProfileController::class, 'update'])
            ->name('api.merchant.profile.update');
    });

/**
 * Webhook API routes (public but with signature verification).
 */
Route::prefix('v1/webhooks')
    ->middleware(['throttle:30,1'])
    ->group(base_path('routes/api/webhooks.php'));
