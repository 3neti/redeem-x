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
    Route::get('', \App\Actions\Api\Transactions\ListTransactions::class)
        ->name('index');
    
    // Get transaction statistics
    // GET /api/v1/transactions/stats
    Route::get('stats', \App\Actions\Api\Transactions\GetTransactionStats::class)
        ->name('stats');

    // Export transactions
    // GET /api/v1/transactions/export
    Route::get('export', \App\Actions\Api\Transactions\ExportTransactions::class)
        ->name('export');

    // Get transaction details
    // GET /api/v1/transactions/{voucher}
    Route::get('{voucher:code}', \App\Actions\Api\Transactions\ShowTransaction::class)
        ->name('show');
    
    // Refresh disbursement status
    // POST /api/v1/transactions/{code}/refresh-status
    // Rate limit: 5 requests per minute per user to prevent gateway abuse
    Route::post('{code}/refresh-status', \App\Actions\Api\Transactions\RefreshDisbursementStatus::class)
        ->middleware('throttle:5,1')
        ->name('refresh-status');
});

// Unified wallet transactions endpoint
// Note: Inherits 'auth:sanctum' from parent group, no additional throttle for testing
Route::prefix('wallet/transactions')
    ->name('api.wallet.transactions.')
    ->withoutMiddleware('throttle:60,1')
    ->group(function () {
        // List all wallet transactions (deposits + withdrawals)
        // GET /api/v1/wallet/transactions
        Route::get('', \App\Actions\Api\Transactions\ListWalletTransactions::class)
            ->name('index');
    });
