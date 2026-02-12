<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use LBHurtado\PwaUi\Http\Controllers\PwaPortalController;
use LBHurtado\PwaUi\Http\Controllers\PwaVoucherController;
use LBHurtado\PwaUi\Http\Controllers\PwaWalletController;
use LBHurtado\PwaUi\Http\Controllers\PwaSettingsController;

/*
|--------------------------------------------------------------------------
| PWA Routes
|--------------------------------------------------------------------------
|
| Mobile-first PWA routes for subscriber experience.
| All routes are prefixed with /pwa and require authentication.
|
*/

Route::middleware(['web', 'auth', ValidateSessionWithWorkOS::class])
    ->prefix('pwa')
    ->name('pwa.')
    ->group(function () {
        // Portal (Home) - Wallet-first dashboard
        Route::get('/portal', [PwaPortalController::class, 'index'])
            ->name('portal');

        // Vouchers
        Route::get('/vouchers', [PwaVoucherController::class, 'index'])
            ->name('vouchers.index');

        Route::get('/vouchers/generate', [PwaVoucherController::class, 'create'])
            ->name('vouchers.create');

        Route::post('/vouchers', [PwaVoucherController::class, 'store'])
            ->name('vouchers.store');

        Route::get('/vouchers/{code}', [PwaVoucherController::class, 'show'])
            ->name('vouchers.show');

        // Wallet
        Route::get('/wallet', [PwaWalletController::class, 'index'])
            ->name('wallet');

        // Settings
        Route::get('/settings', [PwaSettingsController::class, 'index'])
            ->name('settings');

        // Offline fallback page
        Route::get('/offline', function () {
            return Inertia::render('Pwa/Offline');
        })->name('offline');
    });
