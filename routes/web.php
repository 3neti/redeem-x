<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::get('/', fn () => Inertia::render('Welcome'));

// Public QR load page (no authentication required)
Route::get('/load/{uuid}', \App\Http\Controllers\Wallet\LoadPublicController::class)
    ->name('load.public');

// Public voucher inspect page (deprecated): hard redirect to /redeem, preserving ?code
Route::get('/vouchers/inspect', function () {
    $code = request()->query('code');
    if ($code) {
        // Preserve code in query and redirect to redeem start
        return redirect()->route('redeem.start', ['code' => $code]);
    }
    return redirect()->route('redeem.start');
})->name('vouchers.inspect');

// Webhook routes (no authentication required)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('netbank/payment', [\App\Http\Controllers\Webhooks\NetBankWebhookController::class, 'handlePayment'])
        ->name('netbank.payment');
});

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Transaction history routes
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Transactions\TransactionController::class, 'index'])
            ->name('index');
        Route::get('export', [\App\Http\Controllers\Transactions\TransactionController::class, 'export'])
            ->name('export');
    });
    
    // Contact management routes
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Contacts\ContactController::class, 'index'])
            ->name('index');
        Route::get('{contact}', [\App\Http\Controllers\Contacts\ContactController::class, 'show'])
            ->name('show');
    });
    
    // Voucher management routes
    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        Route::get('/', [App\Http\Controllers\Vouchers\VoucherController::class, 'index'])
            ->name('index');
        
        // Voucher generation routes (must be before {voucher} catch-all)
        Route::get('generate', [\App\Http\Controllers\Vouchers\GenerateController::class, 'create'])
            ->name('generate.create');
        Route::post('generate', [\App\Http\Controllers\Vouchers\GenerateController::class, 'store'])
            ->name('generate.store');
        Route::get('generate/success/{count}', [\App\Http\Controllers\Vouchers\GenerateController::class, 'success'])
            ->name('generate.success');
        
        // Bulk voucher generation (SPA - no controller needed)
        Route::get('generate/bulk', fn () => Inertia::render('vouchers/generate/BulkCreate'))
            ->name('generate.bulk');
        
        // Show specific voucher (must be last - catches everything)
        Route::get('{voucher}', [App\Http\Controllers\Vouchers\VoucherController::class, 'show'])
            ->name('show');
    });
    
    // Wallet routes
    Route::prefix('wallet')->name('wallet.')->group(function () {
        // Dashboard
        Route::get('/', [\App\Http\Controllers\Wallet\WalletController::class, 'index'])
            ->name('index');
        
        // QR Load (renamed from 'load')
        Route::get('qr', \App\Http\Controllers\Wallet\QrController::class)
            ->name('qr');
        
        // Backward compatibility redirect
        Route::redirect('load', 'qr', 301);
        
        Route::get('balance', \App\Http\Controllers\Wallet\CheckBalanceController::class)
            ->name('balance');
        Route::get('add-funds', \LBHurtado\PaymentGateway\Http\Controllers\GenerateController::class)
            ->name('add-funds');
    });
    
    // Top-Up routes
    Route::prefix('topup')->name('topup.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Wallet\TopUpController::class, 'index'])
            ->name('index');
        Route::post('/', [\App\Http\Controllers\Wallet\TopUpController::class, 'store'])
            ->name('store');
        Route::get('callback', [\App\Http\Controllers\Wallet\TopUpController::class, 'callback'])
            ->name('callback');
        Route::get('status/{referenceNo}', [\App\Http\Controllers\Wallet\TopUpController::class, 'status'])
            ->name('status');
    });
    
    // User billing routes
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Billing\BillingController::class, 'index'])
            ->name('index');
    });
    
    // Balance monitoring routes (admin only, configurable via .env)
    Route::get('balances', [\App\Http\Controllers\Balances\BalanceController::class, 'index'])
        ->name('balances.index');
    
    // Admin routes (requires super-admin role)
    Route::prefix('admin')
        ->name('admin.')
        ->middleware('admin.override')
        ->group(function () {
            // Pricing management
            Route::prefix('pricing')
                ->name('pricing.')
                ->middleware('permission:manage pricing')
                ->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\PricingController::class, 'index'])
                        ->name('index');
                    Route::get('{item}/edit', [\App\Http\Controllers\Admin\PricingController::class, 'edit'])
                        ->name('edit');
                    Route::patch('{item}', [\App\Http\Controllers\Admin\PricingController::class, 'update'])
                        ->name('update');
                });
            
            // Admin billing (view all users)
            Route::prefix('billing')
                ->name('billing.')
                ->middleware('permission:view all billing')
                ->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\BillingController::class, 'index'])
                        ->name('index');
                    Route::get('{charge}', [\App\Http\Controllers\Admin\BillingController::class, 'show'])
                        ->name('show');
                });
            
            // Global voucher preferences (default settings for all users)
            Route::prefix('preferences')
                ->name('preferences.')
                ->middleware('permission:manage preferences')
                ->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\PreferencesController::class, 'index'])
                        ->name('index');
                    Route::patch('/', [\App\Http\Controllers\Admin\PreferencesController::class, 'update'])
                        ->name('update');
                });
        });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Form Flow demo completion callback
Route::post('/form-flow-complete', function () {
    logger('Form flow completed', request()->all());
    return response()->json([
        'success' => true,
        'message' => 'Form flow completed successfully!',
        'data' => request()->all()
    ]);
})->name('form-flow.complete.callback');

// Development login (bypass WorkOS) - MUST be after auth.php
if (app()->environment('local')) {
    Route::get('/dev-login/{email}', function ($email) {
        $user = \App\Models\User::where('email', $email)->firstOrFail();
        \Illuminate\Support\Facades\Auth::login($user);
        session()->regenerate(); // Important for session to persist
        return redirect('/dashboard');
    })->name('dev.login');
}
