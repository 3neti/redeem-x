<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Transaction API Routes
|--------------------------------------------------------------------------
|
| API endpoints for transaction history and statistics.
| All routes require authentication.
|
*/

Route::prefix('transactions')->name('api.transactions.')->group(function () {
    // List user's transactions (paginated)
    // GET /api/v1/transactions
    Route::get('/', [\App\Actions\Api\Transactions\ListTransactions::class, 'asController'])
        ->name('index');

    // Get transaction statistics
    // GET /api/v1/transactions/stats
    Route::get('/stats', [\App\Actions\Api\Transactions\GetTransactionStats::class, 'asController'])
        ->name('stats');

    // Export transactions
    // GET /api/v1/transactions/export
    Route::get('/export', [\App\Actions\Api\Transactions\ExportTransactions::class, 'asController'])
        ->name('export');

    // Get transaction details
    // GET /api/v1/transactions/{voucher}
    Route::get('/{voucher:code}', [\App\Actions\Api\Transactions\ShowTransaction::class, 'asController'])
        ->name('show');
});
