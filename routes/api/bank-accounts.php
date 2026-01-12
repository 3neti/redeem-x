<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bank Accounts API Routes
|--------------------------------------------------------------------------
|
| API endpoints for managing user bank accounts.
| All routes require authentication.
|
*/

Route::prefix('user/bank-accounts')->name('api.bank-accounts.')->group(function () {
    // List all bank accounts
    // GET /api/v1/user/bank-accounts
    Route::get('', [\App\Actions\Api\BankAccounts\ListBankAccounts::class, 'asController'])
        ->name('index');

    // Create a new bank account
    // POST /api/v1/user/bank-accounts
    Route::post('', [\App\Actions\Api\BankAccounts\CreateBankAccount::class, 'asController'])
        ->name('store');

    // Update a bank account
    // PUT /api/v1/user/bank-accounts/{id}
    Route::put('{id}', [\App\Actions\Api\BankAccounts\UpdateBankAccount::class, 'asController'])
        ->name('update');

    // Delete a bank account
    // DELETE /api/v1/user/bank-accounts/{id}
    Route::delete('{id}', [\App\Actions\Api\BankAccounts\DeleteBankAccount::class, 'asController'])
        ->name('destroy');

    // Set bank account as default
    // PUT /api/v1/user/bank-accounts/{id}/set-default
    Route::put('{id}/set-default', [\App\Actions\Api\BankAccounts\SetDefaultBankAccount::class, 'asController'])
        ->name('set-default');
});
