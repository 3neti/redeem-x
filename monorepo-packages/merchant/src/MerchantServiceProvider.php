<?php

namespace LBHurtado\Merchant;

use Illuminate\Support\ServiceProvider;

class MerchantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/merchant.php',
            'merchant'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/merchant.php' => config_path('merchant.php'),
            ], 'merchant-config');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}
