<?php

use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\CampaignController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
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
    Route::post('settings/profile/toggle-feature', [ProfileController::class, 'toggleFeature'])->name('profile.toggle-feature');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/api-tokens', [ApiTokenController::class, 'index'])->name('settings.api-tokens.index');
    Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])->name('settings.api-tokens.store');
    Route::delete('settings/api-tokens', [ApiTokenController::class, 'destroyAll'])->name('settings.api-tokens.destroy-all');
    Route::delete('settings/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('settings.api-tokens.destroy');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/preferences', function () {
        return Inertia::render('settings/Preferences');
    })->name('preferences.edit');

    Route::get('settings/wallet', [WalletController::class, 'edit'])->name('wallet.edit');
    Route::post('settings/wallet', [WalletController::class, 'store'])->name('wallet.store');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');
    Route::post('settings/security/ip-whitelist', [SecurityController::class, 'updateIpWhitelist'])->name('security.ip-whitelist.update');
    Route::post('settings/security/signature/generate', [SecurityController::class, 'generateSignatureSecret'])->name('security.signature.generate');
    Route::post('settings/security/signature', [SecurityController::class, 'updateSignature'])->name('security.signature.update');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::resource('campaigns', CampaignController::class);
        Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])
            ->name('campaigns.duplicate');
    });
});
