<?php

declare(strict_types=1);

use App\Http\Controllers\Pay\PayVoucherController;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Feature;

/*
|--------------------------------------------------------------------------
| Pay Voucher Routes
|--------------------------------------------------------------------------
|
| Public routes for paying vouchers (payable/settlement types).
| Feature flag check moved to controller - routes always registered.
|
*/

Route::prefix('pay')->name('pay.')->middleware(['web'])->group(function () {
    // Pay page (public UI)
    Route::get('/', [PayVoucherController::class, 'index'])->name('index');

    // Quote endpoint - validate voucher and get payment details
    Route::post('quote', [PayVoucherController::class, 'quote'])
        ->name('quote');

    // QR generation endpoint - create payment QR code
    Route::post('qr', [PayVoucherController::class, 'generateQr'])
        ->name('qr');
});
