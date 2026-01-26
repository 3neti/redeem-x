<?php

namespace App\Providers;

use App\Listeners\NotifyAdminOfDisbursementFailure;
use App\Listeners\UpdateContactKycStatus;
use App\Models\InstructionItem;
use App\Models\User;
use App\Observers\InstructionItemObserver;
use App\Observers\UserObserver;
use App\Policies\VoucherPolicy;
use App\Services\DataEnrichers\DataEnricherRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use LBHurtado\Voucher\Events\DisbursementRequested;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Events\DisbursementFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register data enricher registry as singleton
        $this->app->singleton(DataEnricherRegistry::class, function ($app) {
            return new DataEnricherRegistry();
        });
        
        // Register custom payment gateway with payment classification
        $useOmnipay = filter_var(env('USE_OMNIPAY', false), FILTER_VALIDATE_BOOLEAN);
        
        $this->app->bind(
            \LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface::class,
            $useOmnipay 
                ? \App\Gateways\CustomOmnipayPaymentGateway::class
                : \App\Gateways\CustomNetbankPaymentGateway::class
        );
        
        // Bind voucher generation notification interface to host app implementation
        $this->app->bind(
            \LBHurtado\Voucher\Contracts\VouchersGeneratedNotificationInterface::class,
            \App\Notifications\VouchersGeneratedSummary::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        InstructionItem::observe(InstructionItemObserver::class);

        // Register voucher policy (package model doesn't auto-discover)
        Gate::policy(Voucher::class, VoucherPolicy::class);

        // Register disbursement failure listener
        Event::listen(
            DisbursementFailed::class,
            NotifyAdminOfDisbursementFailure::class
        );
        
        // Register KYC status update listener
        Event::listen(
            DisbursementRequested::class,
            UpdateContactKycStatus::class
        );
        
        // Register payment confirmation SMS job
        Event::listen(
            \App\Events\PaymentDetectedButNotConfirmed::class,
            function (\App\Events\PaymentDetectedButNotConfirmed $event) {
                \App\Jobs\SendPaymentConfirmationSms::dispatch(
                    $event->paymentRequestId,
                    $event->payerMobile,
                    $event->amount,
                    $event->voucherCode
                );
            }
        );

        // Define feature flags
        Feature::define('advanced-pricing-mode', function (User $user) {
            // Super-admins and power-users have advanced mode by default
            return $user->hasAnyRole(['super-admin', 'power-user']);
        });

        Feature::define('beta-features', function (User $user) {
            // Disabled by default, can be manually activated per user
            return false;
        });

        Feature::define('settlement-vouchers', function (?User $user) {
            // Enable in dev/staging for testing (even without authentication)
            if (app()->environment('local', 'staging')) {
                return true;
            }
            // In production, require user-specific activation
            // Guests cannot access (returns false)
            return false;
        });
    }
}
