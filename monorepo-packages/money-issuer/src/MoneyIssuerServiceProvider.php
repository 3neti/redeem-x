<?php

namespace LBHurtado\MoneyIssuer;

use Illuminate\Support\ServiceProvider;
use LBHurtado\MoneyIssuer\Support\BankRegistry;

/**
 * Money Issuer Service Provider
 *
 * Provides Philippine bank and EMI directory services.
 * This is a lightweight package that provides bank data without payment operation dependencies.
 *
 * Features:
 * - Bank/EMI registry (BankRegistry)
 * - Settlement rail restrictions (bank-restrictions.php config)
 * - Publishable banks.json for easy updates
 */
class MoneyIssuerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register BankRegistry singleton
        $this->app->singleton(BankRegistry::class, fn () => new BankRegistry);

        // Merge bank restrictions config
        $this->mergeConfigFrom(
            __DIR__.'/../config/bank-restrictions.php',
            'bank-restrictions'
        );
    }

    public function boot(): void
    {
        // Publish banks.json to app resources
        $this->publishes([
            __DIR__.'/../resources/documents/banks.json' => resource_path('documents/banks.json'),
        ], 'banks-registry');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/bank-restrictions.php' => config_path('bank-restrictions.php'),
        ], 'config');
    }
}
