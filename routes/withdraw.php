<?php

declare(strict_types=1);

use App\Http\Controllers\Withdraw\WithdrawController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Withdraw Routes
|--------------------------------------------------------------------------
|
| Routes for slice-based withdrawal from divisible vouchers.
| These routes are public (no authentication required).
| The redeemer proves identity via mobile number matching.
|
| Flow:
| 1. /withdraw?code=CODE — show voucher info + withdrawal form
| 2. POST /withdraw/{voucher:code} — validate mobile, execute withdrawal
| 3. /withdraw/{voucher:code}/success — confirmation page
|
*/

Route::prefix('withdraw')->name('withdraw.')->group(function () {
    // Show withdrawal page
    Route::get('/', [WithdrawController::class, 'show'])->name('show');

    // Process withdrawal
    Route::post('/{voucher:code}', [WithdrawController::class, 'process'])->name('process');

    // Success page
    Route::get('/{voucher:code}/success', [WithdrawController::class, 'success'])->name('success');
});
