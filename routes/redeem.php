<?php

declare(strict_types=1);

use App\Http\Controllers\Redeem\{KYCRedemptionController, RedeemController, SuccessRedirectController};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Redemption Routes
|--------------------------------------------------------------------------
|
| Routes for voucher redemption flow.
| These routes are public (no authentication required).
|
| Flow:
| 1. Start (/redeem)
| 2. Bank Account (/redeem/{voucher}/wallet)
| 3. Dynamic Plugins (/redeem/{voucher}/{plugin})
| 4. Finalize (/redeem/{voucher}/finalize)
| 5. Confirm (/redeem/{voucher}/confirm)
| 6. Success (/redeem/{voucher}/success)
|
*/

Route::prefix('redeem')->name('redeem.')->group(function () {
    // Start redemption (no voucher yet)
    Route::get('/', [RedeemController::class, 'start'])->name('start');

    // Voucher-specific routes (route model binding by code)
    Route::prefix('{voucher:code}')->group(function () {
        // Step 1: Collect bank account
        Route::get('/wallet', [RedeemController::class, 'wallet'])->name('wallet');
//        Route::post('/wallet', [RedeemWizardController::class, 'storeWallet'])->name('wallet.store');

        // Step 2: Inputs (API-first flow) - Collect email, birthdate, name, etc.
        Route::get('/inputs', [RedeemController::class, 'inputs'])->name('inputs');

        // Step 2a: Location (API-first flow)
        Route::get('/location', [RedeemController::class, 'location'])->name('location');

        // Step 2b: Selfie (API-first flow)
        Route::get('/selfie', [RedeemController::class, 'selfie'])->name('selfie');

        // Step 2c: Signature (API-first flow)
        Route::get('/signature', [RedeemController::class, 'signature'])->name('signature');

        // Store session data (called from frontend before navigation)
        Route::post('/session', [RedeemController::class, 'storeSession'])->name('session.store');

        // Step 2d: Finalize - Review before confirmation
        Route::get('/finalize', [RedeemController::class, 'finalize'])->name('finalize');

        // Step 2e: KYC routes (if required by voucher)
        Route::get('/kyc/initiate', [KYCRedemptionController::class, 'initiate'])->name('kyc.initiate');
        Route::get('/kyc/callback', [KYCRedemptionController::class, 'callback'])->name('kyc.callback');
        Route::get('/kyc/status', [KYCRedemptionController::class, 'status'])->name('kyc.status');

        // Step 3: Finalize and review (wizard flow - must be before plugin route)
        // Note: This is handled by the same route above but renders FinalizeApi for API flow

        // Step 4: Confirm and execute redemption
        Route::post('/confirm', [RedeemController::class, 'confirm'])->name('confirm');

        // Step 5: Success page
        Route::get('/success', [RedeemController::class, 'success'])->name('success');

//        // Step 2: Dynamic plugin-based input collection (must be last)
//        Route::get('/{plugin}', [RedeemWizardController::class, 'plugin'])->name('plugin');
//        Route::post('/{plugin}', [RedeemWizardController::class, 'storePlugin'])->name('plugin.store');
    });
    
    // Step 6: Redirect to external URL (rider URL) - outside model binding to allow redeemed vouchers
    Route::get('/{code}/redirect', SuccessRedirectController::class)->name('redirect');
});
