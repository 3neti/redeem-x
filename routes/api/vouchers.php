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
    // Inspect voucher metadata (public, no auth)
    // GET /api/v1/vouchers/{code}/inspect
    Route::get('/{code}/inspect', [\App\Actions\Api\Vouchers\InspectVoucher::class, 'asController'])
        ->name('inspect')
        ->withoutMiddleware('auth:sanctum');

    // Generate vouchers
    // POST /api/v1/vouchers
    Route::post('/', [\App\Actions\Api\Vouchers\GenerateVouchers::class, 'asController'])
        ->middleware('idempotent')
        ->name('generate');

    // Bulk create vouchers with external metadata
    // POST /api/v1/vouchers/bulk-create
    Route::post('/bulk-create', [\App\Actions\Api\Vouchers\BulkCreateVouchers::class, 'asController'])
        ->middleware('idempotent')
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

    // Generate QR code for voucher redemption
    // GET /api/v1/vouchers/{code}/qr
    Route::get('/{code}/qr', [\App\Actions\Api\Vouchers\GenerateVoucherQr::class, 'asController'])
        ->name('qr');

    // Set external metadata
    // POST /api/v1/vouchers/{voucher}/external
    Route::post('/{voucher:code}/external', [\App\Actions\Api\Vouchers\SetExternalMetadata::class, 'asController'])
        ->name('external.set');

    // Track timing submit (auth required - happens during redemption process)
    // POST /api/v1/vouchers/{voucher}/timing/submit
    Route::post('/{voucher:code}/timing/submit', [\App\Actions\Api\Vouchers\TrackRedemptionSubmit::class, 'asController'])
        ->name('timing.submit');

    // Cancel voucher (if not redeemed)
    // DELETE /api/v1/vouchers/{voucher}
    Route::delete('/{voucher:code}', [\App\Actions\Api\Vouchers\CancelVoucher::class, 'asController'])
        ->name('cancel');
});
