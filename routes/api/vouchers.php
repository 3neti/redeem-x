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

    // Bulk create vouchers with external metadata
    // POST /api/v1/vouchers/bulk-create
    Route::post('/bulk-create', [\App\Actions\Api\Vouchers\BulkCreateVouchers::class, 'asController'])
        ->name('bulk-create');

    // Query vouchers with filters
    // GET /api/v1/vouchers/query
    Route::get('/query', [\App\Actions\Api\Vouchers\QueryVouchers::class, 'asController'])
        ->name('query');

    // List user's vouchers (paginated)
    // GET /api/v1/vouchers
    Route::get('/', [\App\Actions\Api\Vouchers\ListVouchers::class, 'asController'])
        ->name('index');

    // Get voucher details
    // GET /api/v1/vouchers/{voucher}
    Route::get('/{voucher:code}', [\App\Actions\Api\Vouchers\ShowVoucher::class, 'asController'])
        ->name('show');

    // Set external metadata
    // POST /api/v1/vouchers/{voucher}/external
    Route::post('/{voucher:code}/external', [\App\Actions\Api\Vouchers\SetExternalMetadata::class, 'asController'])
        ->name('external.set');

    // Track timing events
    // POST /api/v1/vouchers/{voucher}/timing/click
    Route::post('/{voucher:code}/timing/click', [\App\Actions\Api\Vouchers\TrackClick::class, 'asController'])
        ->name('timing.click');
    // POST /api/v1/vouchers/{voucher}/timing/start
    Route::post('/{voucher:code}/timing/start', [\App\Actions\Api\Vouchers\TrackRedemptionStart::class, 'asController'])
        ->name('timing.start');
    // POST /api/v1/vouchers/{voucher}/timing/submit
    Route::post('/{voucher:code}/timing/submit', [\App\Actions\Api\Vouchers\TrackRedemptionSubmit::class, 'asController'])
        ->name('timing.submit');

    // Cancel voucher (if not redeemed)
    // DELETE /api/v1/vouchers/{voucher}
    Route::delete('/{voucher:code}', [\App\Actions\Api\Vouchers\CancelVoucher::class, 'asController'])
        ->name('cancel');
});
