<?php

declare(strict_types=1);

use App\Http\Controllers\Vouchers\VoucherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Voucher Routes
|--------------------------------------------------------------------------
|
| Routes for voucher generation and management.
| All routes require authentication (WorkOS).
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('vouchers', VoucherController::class)->only([
        'index',   // GET /vouchers - List user's vouchers
        'create',  // GET /vouchers/create - Show generation form
        'store',   // POST /vouchers - Generate vouchers
        'show',    // GET /vouchers/{voucher} - View voucher details
    ]);
});
