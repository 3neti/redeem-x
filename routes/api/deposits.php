<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Deposits & Senders API Routes
|--------------------------------------------------------------------------
|
| API endpoints for deposit history and sender contact management.
| All routes require authentication.
|
*/

Route::prefix('deposits')->name('api.deposits.')->group(function () {
    // List incoming deposits (paginated)
    // GET /api/v1/deposits
    Route::get('', [\App\Actions\Api\Deposits\ListDeposits::class, 'asController'])
        ->name('index');

    // Get deposit statistics
    // GET /api/v1/deposits/stats
    Route::get('stats', [\App\Actions\Api\Deposits\GetDepositStats::class, 'asController'])
        ->name('stats');
});

Route::prefix('senders')->name('api.senders.')->group(function () {
    // List sender contacts (paginated)
    // GET /api/v1/senders
    Route::get('', [\App\Actions\Api\Senders\ListSenders::class, 'asController'])
        ->name('index');

    // Get sender details with transaction history
    // GET /api/v1/senders/{id}
    Route::get('{contactId}', [\App\Actions\Api\Senders\ShowSender::class, 'asController'])
        ->where('contactId', '[0-9]+')
        ->name('show');
});
