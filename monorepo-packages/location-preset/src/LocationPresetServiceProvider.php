<?php

namespace LBHurtado\LocationPreset;

use Illuminate\Support\ServiceProvider;

class LocationPresetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/location-preset.php',
            'location-preset'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/location-preset.php' => config_path('location-preset.php'),
        ], 'config');
    }
}
