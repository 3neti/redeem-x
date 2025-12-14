<?php

declare(strict_types=1);

use App\Http\Controllers\Disburse\DisburseYamlController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Disburse YAML Routes (A/B Testing)
|--------------------------------------------------------------------------
|
| Parallel routes for A/B testing YAML driver vs PHP driver.
| Uses DisburseYamlController which forces YAML mode.
| Reference ID prefix: 'disburse-yaml-' for differentiation.
|
| Flow identical to /disburse:
| 1. Start (/disburse-yaml)
| 2. Initiate Flow (auto-redirect to Form Flow Manager with YAML driver)
| 3. Dynamic Steps (handled by Form Flow Manager)
| 4. Complete (/disburse-yaml/{voucher}/complete) - callback
| 5. Success (/disburse-yaml/{voucher}/success)
|
*/

Route::prefix('disburse-yaml')->name('disburse-yaml.')->group(function () {
    // Start: Enter voucher code
    Route::get('/', [DisburseYamlController::class, 'start'])->name('start');
    
    // Callback after flow completion (does not redeem)
    Route::post('/{voucher:code}/complete', [DisburseYamlController::class, 'complete'])->name('complete');
    
    // Redeem voucher after user confirmation
    Route::post('/{voucher:code}/redeem', [DisburseYamlController::class, 'redeem'])->name('redeem');
    
    // Cancel callback
    Route::get('/cancel', [DisburseYamlController::class, 'cancel'])->name('cancel');
    
    // Success page
    Route::get('/{voucher:code}/success', [DisburseYamlController::class, 'success'])->name('success');
});
