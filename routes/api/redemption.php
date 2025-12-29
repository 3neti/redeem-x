<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Redemption API Routes
|--------------------------------------------------------------------------
|
| Public API endpoints for voucher redemption flow.
| No authentication required - uses signed tokens for state management.
|
| Flow:
| 1. POST /validate - Validate voucher code
| 2. POST /start - Start redemption with mobile number
| 3. POST /wallet - Submit bank account details
| 4. POST /plugin - Submit plugin-specific data (repeatable)
| 5. GET /finalize - Get redemption summary
| 6. POST /confirm - Execute redemption
| 7. GET /status/{code} - Check redemption status
|
*/

Route::prefix('redeem')->name('api.redeem.')->group(function () {
    // Validate voucher code
    // POST /api/v1/redeem/validate
    Route::post('validate', [\App\Actions\Api\Redemption\ValidateRedemptionCode::class, 'asController'])
        ->name('validate');

    // Start redemption session
    // POST /api/v1/redeem/start
    Route::post('start', [\App\Actions\Api\Redemption\StartRedemption::class, 'asController'])
        ->name('start');

    // Submit wallet information
    // POST /api/v1/redeem/wallet
    Route::post('wallet', [\App\Actions\Api\Redemption\SubmitWallet::class, 'asController'])
        ->name('wallet');

    // Submit plugin data
    // POST /api/v1/redeem/plugin
    Route::post('plugin', [\App\Actions\Api\Redemption\SubmitPlugin::class, 'asController'])
        ->name('plugin');

    // Get finalization summary
    // GET /api/v1/redeem/finalize
    Route::get('finalize', [\App\Actions\Api\Redemption\FinalizeRedemption::class, 'asController'])
        ->name('finalize');

    // Confirm and execute redemption
    // POST /api/v1/redeem/confirm
    Route::post('confirm', [\App\Actions\Api\Redemption\ConfirmRedemption::class, 'asController'])
        ->name('confirm');

    // Check redemption status
    // GET /api/v1/redeem/status/{code}
    Route::get('status/{code}', [\App\Actions\Api\Redemption\GetRedemptionStatus::class, 'asController'])
        ->name('status');
});
