<?php

declare(strict_types=1);

namespace LBHurtado\OgMeta;

use Illuminate\Support\ServiceProvider;
use LBHurtado\OgMeta\Http\Middleware\InjectOgMeta;
use LBHurtado\OgMeta\Services\OgImageRenderer;
use LBHurtado\OgMeta\Services\OgMetaService;

class OgMetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/og-meta.php', 'og-meta');

        $this->app->singleton(OgImageRenderer::class);

        $this->app->singleton(OgMetaService::class, function ($app) {
            return new OgMetaService(
                renderer: $app->make(OgImageRenderer::class),
                resolvers: config('og-meta.resolvers', []),
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/og.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'og-meta');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/og-meta.php' => config_path('og-meta.php'),
            ], 'og-meta-config');
        }

        // Register middleware alias
        $this->app['router']->aliasMiddleware('og-meta', InjectOgMeta::class);
    }
}
