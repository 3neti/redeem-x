<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use LBHurtado\PwaUi\Http\Controllers\PwaPortalController;
use LBHurtado\PwaUi\Http\Controllers\PwaVoucherController;
use LBHurtado\PwaUi\Http\Controllers\PwaWalletController;
use LBHurtado\PwaUi\Http\Controllers\PwaTopUpController;
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

        Route::post('/vouchers/{code}/lock', [PwaVoucherController::class, 'lock'])
            ->name('vouchers.lock');

        Route::post('/vouchers/{code}/unlock', [PwaVoucherController::class, 'unlock'])
            ->name('vouchers.unlock');

        Route::post('/vouchers/{code}/close', [PwaVoucherController::class, 'close'])
            ->name('vouchers.close');

        Route::post('/vouchers/{code}/cancel', [PwaVoucherController::class, 'cancel'])
            ->name('vouchers.cancel');

        Route::post('/vouchers/{code}/invalidate', [PwaVoucherController::class, 'invalidate'])
            ->name('vouchers.invalidate');

        Route::post('/vouchers/{code}/extend-expiration', [PwaVoucherController::class, 'extendExpiration'])
            ->name('vouchers.extend-expiration');

        // Wallet
        Route::get('/wallet', [PwaWalletController::class, 'index'])
            ->name('wallet');

        // Top-Up
        Route::get('/topup', [PwaTopUpController::class, 'index'])
            ->middleware('requires.mobile')
            ->name('topup');

        // Settings
        Route::get('/settings', [PwaSettingsController::class, 'index'])
            ->name('settings');

        // Offline fallback page
        Route::get('/offline', function () {
            return Inertia::render('Pwa/Offline');
        })->name('offline');
    });
