<?php

declare(strict_types=1);

use App\Http\Controllers\Pay\PayVoucherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pay Voucher Routes
|--------------------------------------------------------------------------
|
| Public routes for paying vouchers (payable/settlement types).
| Protected by feature flag + security middleware stack.
|
*/

Route::prefix('pay')->name('pay.')->group(function () {
    // Pay page (public UI)
    Route::get('/', [PayVoucherController::class, 'index'])->name('index');
    
    // Quote endpoint - validate voucher and get payment details
    Route::post('quote', [PayVoucherController::class, 'quote'])
        ->middleware(['idempotent', 'ip.whitelist', 'signature.verify', 'rate.limit.advanced'])
        ->name('quote');
    
    // QR generation endpoint - create payment QR code
    Route::post('qr', [PayVoucherController::class, 'generateQr'])
        ->middleware(['idempotent', 'ip.whitelist', 'signature.verify', 'rate.limit.advanced'])
        ->name('qr');
});
