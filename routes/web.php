<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::get('/', fn () => Inertia::render('Welcome'));

// Public health check (no authentication for uptime monitoring)
Route::get('/health', function () {
    return app(\App\Actions\Api\System\GetHealth::class)->simple();
})->name('health.simple');

// Public QR load page (no authentication required)
Route::get('/load/{uuid}', \App\Http\Controllers\Wallet\LoadPublicController::class)
    ->name('load.public');

// Portal route moved to authenticated group below (requires mobile + balance)

// Public voucher inspect page (deprecated): hard redirect to /redeem, preserving ?code
Route::get('/vouchers/inspect', function () {
    $code = request()->query('code');
    if ($code) {
        // Preserve code in query and redirect to redeem start
        return redirect()->route('redeem.start', ['code' => $code]);
    }

    return redirect()->route('redeem.start');
})->name('vouchers.inspect');

// Documentation routes (public - for bank integration teams)
Route::prefix('documentation')->name('documentation.')->group(function () {
    Route::get('/', [\App\Http\Controllers\DocsController::class, 'index'])
        ->name('index');
    Route::get('{slug}', [\App\Http\Controllers\DocsController::class, 'show'])
        ->name('show');
});

// Webhook routes (no authentication required)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('netbank/payment', [\App\Http\Controllers\Webhooks\NetBankWebhookController::class, 'handlePayment'])
        ->name('netbank.payment');
});

// Payment confirmation (signed URL from SMS)
Route::get('/pay/confirm/{paymentRequest}', \App\Actions\Pay\ConfirmPaymentViaSms::class)
    ->middleware('signed')
    ->name('pay.confirm');

// Voucher media viewer (signed URLs for SMS magic links)
Route::get('/voucher/{code}/media/{type}', [\App\Http\Controllers\VoucherMediaController::class, 'show'])
    ->middleware('signed')
    ->name('voucher.media.show');

// Public thank you page after payment confirmation
Route::get('/pay/confirmed/{paymentRequest}', function (\App\Models\PaymentRequest $paymentRequest) {
    return Inertia::render('PaymentConfirmed', [
        'voucherCode' => $paymentRequest->voucher->code,
        'amount' => $paymentRequest->getAmountInMajorUnits(),
        'currency' => $paymentRequest->currency,
        'timestamp' => $paymentRequest->updated_at->toIso8601String(),
    ]);
})->name('pay.confirmed');

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    // Portal landing page (requires onboarding: mobile + balance)
    Route::get('/portal', [\App\Http\Controllers\PortalController::class, 'show'])
        ->middleware(['requires.mobile', 'requires.balance'])
        ->name('portal');

    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
        ->name('dashboard');

    // Transaction history routes
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Transactions\TransactionController::class, 'index'])
            ->name('index');
        Route::get('export', [\App\Http\Controllers\Transactions\TransactionController::class, 'export'])
            ->name('export');
    });

    // Reports routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Reports\ReportsController::class, 'index'])
            ->name('index');
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
        // Note: Form submits to API endpoint (POST /api/v1/vouchers), not these routes
        Route::get('generate', function () {
            $useV2 = config('generate.feature_flags.progressive_disclosure', false);

            // Check user preference override
            $savedMode = 'simple'; // Default
            if (auth()->check()) {
                $user = auth()->user();

                // Debug: Log user preferences
                \Log::info('[Generate Route] User authenticated', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'ui_preferences' => $user->ui_preferences,
                    'ui_preferences_type' => gettype($user->ui_preferences),
                ]);

                $userPreference = $user->ui_preferences['voucher_generate_ui_version'] ?? null;
                if ($userPreference === 'legacy') {
                    $useV2 = false;
                }

                // Read saved Simple/Advanced mode preference
                $savedMode = $user->ui_preferences['voucher_generate_mode'] ?? 'simple';

                \Log::info('[Generate Route] Mode preference read', [
                    'saved_mode' => $savedMode,
                    'has_key' => isset($user->ui_preferences['voucher_generate_mode']),
                    'raw_value' => $user->ui_preferences['voucher_generate_mode'] ?? 'KEY_NOT_SET',
                ]);
            } else {
                \Log::info('[Generate Route] User not authenticated, using default mode');
            }

            $component = $useV2 ? 'vouchers/generate/CreateV2' : 'vouchers/generate/Create';

            \Log::info('[Generate Route] Rendering component', [
                'component' => $component,
                'saved_mode' => $savedMode,
            ]);

            // Load envelope drivers for envelope configuration card
            $envelopeDrivers = [];
            try {
                $driverService = app(\LBHurtado\SettlementEnvelope\Services\DriverService::class);
                $driverList = $driverService->list();
                \Log::info('[Generate Route] Envelope drivers found', ['count' => count($driverList)]);
                $envelopeDrivers = collect($driverList)->map(function ($item) use ($driverService) {
                    try {
                        $driver = $driverService->load($item['id'], $item['version']);

                        return [
                            'id' => $driver->id,
                            'version' => $driver->version,
                            'title' => $driver->title,
                            'description' => $driver->description,
                            'domain' => $driver->domain,
                            'documents_count' => $driver->documents->count(),
                            'checklist_count' => $driver->checklist->count(),
                            'signals_count' => $driver->signals->count(),
                            'gates_count' => $driver->gates->count(),
                            'payload_schema' => $driver->payload->schema->inline,
                        ];
                    } catch (\Exception $e) {
                        \Log::warning('[Generate Route] Failed to load driver', ['driver' => $item, 'error' => $e->getMessage()]);

                        return null;
                    }
                })->filter()->values()->all();
                \Log::info('[Generate Route] Envelope drivers loaded', ['drivers' => $envelopeDrivers]);
            } catch (\Exception $e) {
                \Log::error('[Generate Route] DriverService error', ['error' => $e->getMessage()]);
            }

            return Inertia::render($component, [
                'input_field_options' => \LBHurtado\Voucher\Enums\VoucherInputField::options(),
                'config' => config('generate'),
                'saved_mode' => $savedMode, // Pass saved mode to frontend
                'settlement_enabled' => \Laravel\Pennant\Feature::active('settlement-vouchers'),
                'envelope_drivers' => $envelopeDrivers,
            ]);
        })->middleware(['requires.mobile', 'requires.balance'])->name('generate.create');

        // Legacy UI route (always uses Create.vue)
        Route::get('generate/legacy', fn () => Inertia::render('vouchers/generate/Create', [
            'input_field_options' => \LBHurtado\Voucher\Enums\VoucherInputField::options(),
            'config' => config('generate'),
        ]))->name('generate.legacy');

        Route::get('generate/success/{count}', function (int $count) {
            $vouchers = auth()->user()
                ->vouchers()
                ->latest()
                ->take($count)
                ->get();

            if ($vouchers->isEmpty()) {
                abort(404, 'No vouchers found');
            }

            return Inertia::render('vouchers/generate/Success', [
                'vouchers' => $vouchers->map(function ($voucher) {
                    return [
                        'id' => $voucher->id,
                        'code' => $voucher->code,
                        'amount' => $voucher->instructions->cash->amount ?? 0,
                        'currency' => $voucher->instructions->cash->currency ?? 'PHP',
                        'status' => $voucher->status ?? 'active',
                        'expires_at' => $voucher->expires_at?->toIso8601String(),
                        'created_at' => $voucher->created_at->toIso8601String(),
                    ];
                }),
                'batch_id' => 'batch-'.now()->timestamp,
                'count' => $vouchers->count(),
                'total_value' => $vouchers->sum(function ($v) {
                    return $v->instructions->cash->amount ?? 0;
                }),
            ]);
        })->name('generate.success');

        // Bulk voucher generation (SPA - no controller needed)
        Route::get('generate/bulk', fn () => Inertia::render('vouchers/generate/BulkCreate'))
            ->middleware(['requires.mobile', 'requires.balance'])
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

    // Top-Up routes (bank-based external money)
    Route::prefix('topup')->name('topup.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Wallet\TopUpController::class, 'index'])
            ->middleware('requires.mobile')
            ->name('index');
        Route::post('/', [\App\Http\Controllers\Wallet\TopUpController::class, 'store'])
            ->middleware('role:super-admin')
            ->name('store');
        Route::get('callback', [\App\Http\Controllers\Wallet\TopUpController::class, 'callback'])
            ->name('callback');
        Route::get('status/{referenceNo}', [\App\Http\Controllers\Wallet\TopUpController::class, 'status'])
            ->name('status');
    });

    // Payment routes (voucher-based internal transfers)
    Route::prefix('pay')->name('pay.')->group(function () {
        Route::post('voucher', [\App\Http\Controllers\Payment\PaymentController::class, 'voucher'])
            ->middleware('throttle:5,1')
            ->name('voucher');
    });

    // Balance monitoring routes (admin only, configurable via .env)
    Route::get('balances', [\App\Http\Controllers\Balances\BalanceController::class, 'index'])
        ->middleware(['admin.override', 'permission:view balance'])
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
require __DIR__.'/contribute.php';

// Development login (bypass WorkOS) - MUST be after auth.php
if (app()->environment('local')) {
    Route::get('/dev-login/{email}', function ($email) {
        $user = \App\Models\User::where('email', $email)->firstOrFail();
        \Illuminate\Support\Facades\Auth::login($user);
        session()->regenerate(); // Important for session to persist

        return redirect('/dashboard');
    })->name('dev.login');
}
