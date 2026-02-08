<?php

declare(strict_types=1);

use App\Http\Controllers\Contribute\ContributeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contribution Routes
|--------------------------------------------------------------------------
|
| Public routes for external document contribution to settlement envelopes.
| These routes use signed URLs for security - no authentication required.
|
*/

Route::prefix('contribute')->name('contribute.')->middleware(['web'])->group(function () {
    // Main contribution page (signed URL required)
    // Note: Using explicit path to avoid trailing slash redirect issues with signed URLs
    Route::get('', [ContributeController::class, 'show'])
        ->middleware('signed')
        ->name('show');

    // Password verification (for password-protected links)
    Route::post('/verify-password', [ContributeController::class, 'verifyPassword'])
        ->name('verify-password');

    // Document upload endpoint
    Route::post('/upload', [ContributeController::class, 'upload'])
        ->name('upload');

    // Payload update endpoint
    Route::post('/payload', [ContributeController::class, 'updatePayload'])
        ->name('payload');

    // Delete attachment endpoint (only pending)
    Route::post('/delete', [ContributeController::class, 'delete'])
        ->name('delete');
});
