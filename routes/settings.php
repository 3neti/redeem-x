<?php

use App\Http\Controllers\Settings\CampaignController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\Settings\WalletController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/preferences', function () {
        return Inertia::render('settings/Preferences');
    })->name('preferences.edit');

    Route::get('settings/wallet', [WalletController::class, 'edit'])->name('wallet.edit');
    Route::post('settings/wallet', [WalletController::class, 'store'])->name('wallet.store');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::resource('campaigns', CampaignController::class);
        Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])
            ->name('campaigns.duplicate');
    });
});
