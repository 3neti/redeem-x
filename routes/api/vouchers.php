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
    Route::get('{code}/inspect', [\App\Actions\Api\Vouchers\InspectVoucher::class, 'asController'])
        ->name('inspect')
        ->withoutMiddleware('auth:sanctum');

    // Generate vouchers
    // POST /api/v1/vouchers
    Route::post('', [\App\Actions\Api\Vouchers\GenerateVouchers::class, 'asController'])
        ->middleware('idempotent')
        ->name('generate');

    // Bulk create vouchers with external metadata
    // POST /api/v1/vouchers/bulk-create
    Route::post('bulk-create', [\App\Actions\Api\Vouchers\BulkCreateVouchers::class, 'asController'])
        ->middleware('idempotent')
        ->name('bulk-create');

    // Query vouchers with filters
    // GET /api/v1/vouchers/query
    Route::get('query', [\App\Actions\Api\Vouchers\QueryVouchers::class, 'asController'])
        ->name('query');

    // List user's vouchers (paginated)
    // GET /api/v1/vouchers
    Route::get('', [\App\Actions\Api\Vouchers\ListVouchers::class, 'asController'])
        ->name('index');

    // Get voucher details
    // GET /api/v1/vouchers/{voucher}
    Route::get('{voucher:code}', [\App\Actions\Api\Vouchers\ShowVoucher::class, 'asController'])
        ->name('show');

    // Generate QR code for voucher redemption
    // GET /api/v1/vouchers/{code}/qr
    Route::get('{code}/qr', [\App\Actions\Api\Vouchers\GenerateVoucherQr::class, 'asController'])
        ->name('qr');

    // Set external metadata
    // POST /api/v1/vouchers/{voucher}/external
    Route::post('{voucher:code}/external', [\App\Actions\Api\Vouchers\SetExternalMetadata::class, 'asController'])
        ->name('external.set');

    // Track timing submit (auth required - happens during redemption process)
    // POST /api/v1/vouchers/{voucher}/timing/submit
    Route::post('{voucher:code}/timing/submit', [\App\Actions\Api\Vouchers\TrackRedemptionSubmit::class, 'asController'])
        ->name('timing.submit');

    // Cancel voucher (if not redeemed)
    // DELETE /api/v1/vouchers/{voucher}
    Route::delete('{voucher:code}', [\App\Actions\Api\Vouchers\CancelVoucher::class, 'asController'])
        ->name('cancel');
    
    // Manually confirm payment (owner only)
    // POST /api/v1/vouchers/confirm-payment
    Route::post('confirm-payment', \App\Actions\Api\Vouchers\ConfirmPayment::class)
        ->name('confirm-payment');
    
    // Get pending payment requests (owner only)
    // GET /api/v1/vouchers/{code}/pending-payments
    Route::get('{code}/pending-payments', \App\Actions\Api\Vouchers\GetPendingPaymentRequests::class)
        ->name('pending-payments');
    
    // State management actions (owner only)
    // POST /api/v1/vouchers/lock
    Route::post('lock', \App\Actions\Api\Vouchers\LockVoucher::class)
        ->name('lock');
    
    // POST /api/v1/vouchers/unlock
    Route::post('unlock', \App\Actions\Api\Vouchers\UnlockVoucher::class)
        ->name('unlock');
    
    // POST /api/v1/vouchers/force-close
    Route::post('force-close', \App\Actions\Api\Vouchers\ForceCloseVoucher::class)
        ->name('force-close');
    
    // Collect payments from settlement voucher (owner only)
    // POST /api/v1/vouchers/{code}/collect
    Route::post('{code}/collect', \App\Actions\Api\Vouchers\CollectPayments::class)
        ->name('collect');
    
    // =========================================================================
    // Envelope Actions (for settlement envelope workflow)
    // =========================================================================
    Route::prefix('{voucher:code}/envelope')->name('envelope.')->group(function () {
        // Lock envelope (transition to LOCKED state)
        Route::post('lock', [\App\Http\Controllers\Api\V1\EnvelopeActionController::class, 'lock'])
            ->name('lock');
        
        // Settle envelope (transition to SETTLED state, triggers disbursement)
        Route::post('settle', [\App\Http\Controllers\Api\V1\EnvelopeActionController::class, 'settle'])
            ->name('settle');
        
        // Cancel envelope (requires reason)
        Route::post('cancel', [\App\Http\Controllers\Api\V1\EnvelopeActionController::class, 'cancel'])
            ->name('cancel');
        
        // Reopen locked envelope (requires reason)
        Route::post('reopen', [\App\Http\Controllers\Api\V1\EnvelopeActionController::class, 'reopen'])
            ->name('reopen');
        
        // Set signal value
        Route::post('signals/{key}', [\App\Http\Controllers\Api\V1\EnvelopeActionController::class, 'setSignal'])
            ->name('signals.set');
        
        // Update payload
        Route::patch('payload', [\App\Http\Controllers\Api\V1\EnvelopeActionController::class, 'updatePayload'])
            ->name('payload.update');
        
        // Upload attachment
        Route::post('attachments', [\App\Http\Controllers\Api\V1\EnvelopeActionController::class, 'uploadAttachment'])
            ->name('attachments.upload');
    });
});

// Envelope attachment review actions (separate route group with envelope ID)
Route::prefix('envelopes/{envelope}')->name('api.envelopes.')->group(function () {
    // Accept attachment
    Route::post('attachments/{attachment}/accept', [\App\Http\Controllers\Api\V1\EnvelopeActionController::class, 'acceptAttachment'])
        ->name('attachments.accept');
    
    // Reject attachment (requires reason)
    Route::post('attachments/{attachment}/reject', [\App\Http\Controllers\Api\V1\EnvelopeActionController::class, 'rejectAttachment'])
        ->name('attachments.reject');
});
