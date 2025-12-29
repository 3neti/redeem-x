<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/vouchers.php'));
            Route::middleware('web')->group(base_path('routes/redeem.php'));
            Route::middleware('web')->group(base_path('routes/disburse.php'));
            Route::middleware('web')->group(base_path('routes/auth.php'));
            Route::middleware('web')->group(base_path('routes/settings.php'));
            
            // Test routes for KYC (only in non-production)
            if (!app()->environment('production')) {
                Route::middleware('web')->group(base_path('routes/test-kyc.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\CheckIpWhitelist::class,
            \App\Http\Middleware\AdvancedRateLimiting::class,
        ]);

        // Enable session for API routes (required for Sanctum SPA authentication)
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        // Register Spatie Permission middleware aliases
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'admin.override' => \App\Http\Middleware\AllowAdminOverride::class,
            'idempotent' => \App\Http\Middleware\EnsureIdempotentRequest::class,
            'ip.whitelist' => \App\Http\Middleware\CheckIpWhitelist::class,
            'rate.limit.advanced' => \App\Http\Middleware\AdvancedRateLimiting::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle invalid settlement rail exceptions gracefully
        $exceptions->render(function (\LBHurtado\Voucher\Exceptions\InvalidSettlementRailException $e, $request) {
            // Log at WARNING level (not ERROR) since this is validation, not a system error
            \Illuminate\Support\Facades\Log::warning('[InvalidSettlementRail] User attempted invalid rail combination', [
                'message' => $e->getMessage(),
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);
            
            // Return clean JSON response for API/AJAX requests
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'type' => 'invalid_settlement_rail',
                ], 422);
            }
            
            // For web requests, redirect back with error message
            return redirect()->back()->withErrors([
                'settlement_rail' => $e->getMessage(),
            ]);
        });
    })->create();
