<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Transaction history routes
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TransactionController::class, 'index'])
            ->name('index');
        Route::get('export', [\App\Http\Controllers\TransactionController::class, 'export'])
            ->name('export');
    });
    
    // Contact management routes
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ContactController::class, 'index'])
            ->name('index');
        Route::get('{contact}', [\App\Http\Controllers\ContactController::class, 'show'])
            ->name('show');
    });
    
    // Voucher management routes
    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        Route::get('/', [App\Http\Controllers\Voucher\VoucherController::class, 'index'])
            ->name('index');
        
        // Voucher generation routes (must be before {voucher} catch-all)
        Route::get('generate', [\App\Http\Controllers\VoucherGenerationController::class, 'create'])
            ->name('generate.create');
        Route::post('generate', [\App\Http\Controllers\VoucherGenerationController::class, 'store'])
            ->name('generate.store');
        Route::get('generate/success/{count}', [\App\Http\Controllers\VoucherGenerationController::class, 'success'])
            ->name('generate.success');
        
        // Show specific voucher (must be last - catches everything)
        Route::get('{voucher}', [App\Http\Controllers\Voucher\VoucherController::class, 'show'])
            ->name('show');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Development login (bypass WorkOS) - MUST be after auth.php
if (app()->environment('local')) {
    Route::get('/dev-login/{email}', function ($email) {
        $user = \App\Models\User::where('email', $email)->firstOrFail();
        \Illuminate\Support\Facades\Auth::login($user);
        session()->regenerate(); // Important for session to persist
        return redirect('/dashboard');
    })->name('dev.login');
}
