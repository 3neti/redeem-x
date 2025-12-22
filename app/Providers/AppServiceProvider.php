<?php

namespace App\Providers;

use App\Listeners\NotifyAdminOfDisbursementFailure;
use App\Models\InstructionItem;
use App\Models\User;
use App\Observers\InstructionItemObserver;
use App\Observers\UserObserver;
use App\Services\DataEnrichers\DataEnricherRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        InstructionItem::observe(InstructionItemObserver::class);

        // Register disbursement failure listener
        Event::listen(
            DisbursementFailed::class,
            NotifyAdminOfDisbursementFailure::class
        );
    }
}
