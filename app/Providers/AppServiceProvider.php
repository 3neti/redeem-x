<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Services\DataEnrichers\DataEnricherRegistry;
use Illuminate\Support\ServiceProvider;

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
    }
}
