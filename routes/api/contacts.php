<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contact API Routes
|--------------------------------------------------------------------------
|
| API endpoints for contact management.
| All routes require authentication.
|
*/

Route::prefix('contacts')->name('api.contacts.')->group(function () {
    // List user's contacts (paginated)
    // GET /api/v1/contacts
    Route::get('/', [\App\Actions\Api\Contacts\ListContacts::class, 'asController'])
        ->name('index');

    // Get contact details
    // GET /api/v1/contacts/{contact}
    Route::get('/{contact}', [\App\Actions\Api\Contacts\ShowContact::class, 'asController'])
        ->name('show');

    // Get contact's vouchers
    // GET /api/v1/contacts/{contact}/vouchers
    Route::get('/{contact}/vouchers', [\App\Actions\Api\Contacts\GetContactVouchers::class, 'asController'])
        ->name('vouchers');
});
