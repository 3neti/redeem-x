<?php

namespace LBHurtado\PwaUi;

use Illuminate\Support\ServiceProvider;

class PwaUiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/pwa-ui.php', 'pwa-ui'
        );
    }

    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/pwa.php');

        // Publish PWA assets (service worker, manifest, icons)
        $this->publishes([
            __DIR__.'/../resources/pwa' => public_path('pwa'),
        ], 'pwa-assets');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/pwa-ui.php' => config_path('pwa-ui.php'),
        ], 'pwa-config');
    }
}
