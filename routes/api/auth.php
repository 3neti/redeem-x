<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication API Routes
|--------------------------------------------------------------------------
|
| API endpoints for authentication and API token management.
| All routes require Sanctum authentication.
|
*/

Route::prefix('auth')->name('api.auth.')->group(function () {
    // Get authenticated user info
    // GET /api/v1/auth/me
    Route::get('/me', [\App\Actions\Api\Auth\GetAuthenticatedUser::class, 'asController'])
        ->name('me');

    // Token management endpoints
    Route::prefix('tokens')->name('tokens.')->group(function () {
        // List all user's tokens
        // GET /api/v1/auth/tokens
        Route::get('/', [\App\Actions\Api\Auth\ListTokens::class, 'asController'])
            ->name('index');

        // Create new token
        // POST /api/v1/auth/tokens
        Route::post('/', [\App\Actions\Api\Auth\CreateToken::class, 'asController'])
            ->name('store');

        // Revoke all tokens
        // DELETE /api/v1/auth/tokens
        Route::delete('/', [\App\Actions\Api\Auth\RevokeAllTokens::class, 'asController'])
            ->name('destroy-all');

        // Revoke specific token
        // DELETE /api/v1/auth/tokens/{tokenId}
        Route::delete('/{tokenId}', [\App\Actions\Api\Auth\RevokeToken::class, 'asController'])
            ->where('tokenId', '[0-9]+')
            ->name('destroy');
    });
});
