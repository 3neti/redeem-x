<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Voucher API Routes
|--------------------------------------------------------------------------
|
| API endpoints for voucher generation and management.
| All routes require authentication.
|
*/

Route::prefix('vouchers')->name('api.vouchers.')->group(function () {
    // Generate vouchers
    // POST /api/v1/vouchers
    Route::post('/', [\App\Actions\Api\Vouchers\GenerateVouchers::class, 'asController'])
        ->name('generate');

    // List user's vouchers (paginated)
    // GET /api/v1/vouchers
    Route::get('/', [\App\Actions\Api\Vouchers\ListVouchers::class, 'asController'])
        ->name('index');

    // Get voucher details
    // GET /api/v1/vouchers/{voucher}
    Route::get('/{voucher:code}', [\App\Actions\Api\Vouchers\ShowVoucher::class, 'asController'])
        ->name('show');

    // Cancel voucher (if not redeemed)
    // DELETE /api/v1/vouchers/{voucher}
    Route::delete('/{voucher:code}', [\App\Actions\Api\Vouchers\CancelVoucher::class, 'asController'])
        ->name('cancel');
});
