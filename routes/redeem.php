<?php

declare(strict_types=1);

use App\Http\Controllers\Redeem\{RedeemController, RedeemWizardController};
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
        Route::post('/wallet', [RedeemWizardController::class, 'storeWallet'])->name('wallet.store');

        // Step 2a: Location (API-first flow)
        Route::get('/location', [RedeemController::class, 'location'])->name('location');

        // Step 2b: Signature (API-first flow)
        Route::get('/signature', [RedeemController::class, 'signature'])->name('signature');

        // Step 3: Finalize and review (must be before plugin route)
        Route::get('/finalize', [RedeemWizardController::class, 'finalize'])->name('finalize');

        // Step 4: Confirm and execute redemption
        Route::post('/confirm', [RedeemController::class, 'confirm'])->name('confirm');

        // Step 5: Success page
        Route::get('/success', [RedeemController::class, 'success'])->name('success');

        // Step 2: Dynamic plugin-based input collection (must be last)
        Route::get('/{plugin}', [RedeemWizardController::class, 'plugin'])->name('plugin');
        Route::post('/{plugin}', [RedeemWizardController::class, 'storePlugin'])->name('plugin.store');
    });
});
