<?php

declare(strict_types=1);

use App\Http\Controllers\Disburse\DisburseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Disburse Routes
|--------------------------------------------------------------------------
|
| Routes for dynamic voucher redemption using Form Flow Manager.
| These routes are public (no authentication required).
|
| Flow:
| 1. Start (/disburse)
| 2. Initiate Flow (auto-redirect to Form Flow Manager)
| 3. Dynamic Steps (handled by Form Flow Manager)
| 4. Complete (/disburse/{voucher}/complete) - callback
| 5. Success (/disburse/{voucher}/success)
|
*/

Route::prefix('disburse')->name('disburse.')->group(function () {
    // Start: Enter voucher code
    Route::get('/', [DisburseController::class, 'start'])->name('start');
    
    // Callback after flow completion
    Route::post('/{voucher:code}/complete', [DisburseController::class, 'complete'])->name('complete');
    
    // Cancel callback
    Route::get('/cancel', [DisburseController::class, 'cancel'])->name('cancel');
    
    // Success page
    Route::get('/{voucher:code}/success', [DisburseController::class, 'success'])->name('success');
});
