<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerSelfie;

use Illuminate\Support\ServiceProvider;

/**
 * Selfie Handler Service Provider
 * 
 * Registers the selfie handler with the form flow system.
 */
class SelfieHandlerServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/selfie-handler.php',
            'selfie-handler'
        );
        
        // Register SelfieHandler as singleton
        $this->app->singleton(SelfieHandler::class, function ($app) {
            return new SelfieHandler();
        });
    }
    
    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/selfie-handler.php' => config_path('selfie-handler.php'),
        ], 'selfie-handler-config');
        
        // Publish frontend assets (Vue components, composables)
        $this->publishes([
            __DIR__.'/../stubs/resources/js/pages/form-flow/selfie' => resource_path('js/pages/form-flow/selfie'),
        ], 'selfie-handler-stubs');
        
        // Auto-register handler with form-flow-manager
        $this->registerHandler();
    }
    
    /**
     * Register the selfie handler with form-flow-manager
     */
    protected function registerHandler(): void
    {
        // Get current handlers from config
        $handlers = config('form-flow.handlers', []);
        
        // Add selfie handler
        $handlers['selfie'] = SelfieHandler::class;
        
        // Update config
        config(['form-flow.handlers' => $handlers]);
    }
}
