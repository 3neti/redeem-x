<?php

use App\Actions\Api\Wallet\GetBalance;
use App\Actions\Api\Wallet\GetTopUpStatus;
use App\Actions\Api\Wallet\InitiateTopUp;
use App\Actions\Api\Wallet\ListTopUps;
use App\Actions\Api\Wallet\ListTransactions;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet & Top-Up API Routes
|--------------------------------------------------------------------------
|
| These routes handle wallet balance, top-ups, and transaction history.
| All routes require authentication via Sanctum tokens.
|
*/

// Note: These routes are included within the 'v1' prefix and 'auth:sanctum' middleware
// from routes/api.php, so we only need to add the 'wallet' prefix here.
Route::prefix('wallet')->group(function () {
    // Wallet Balance
    Route::get('balance', GetBalance::class)->name('api.wallet.balance');

    // Top-Up Management
    Route::post('topup', InitiateTopUp::class)
        ->middleware('idempotent')
        ->name('api.wallet.topup.initiate');
    Route::get('topup', ListTopUps::class)->name('api.wallet.topup.list');
    Route::get('topup/{referenceNo}', GetTopUpStatus::class)->name('api.wallet.topup.status');

    // Transaction History
    Route::get('transactions', ListTransactions::class)->name('api.wallet.transactions');
});
