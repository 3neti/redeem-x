<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerSignature;

use Illuminate\Support\ServiceProvider;

/**
 * Signature Handler Service Provider
 * 
 * Registers the signature handler with the form flow system.
 */
class SignatureHandlerServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/signature-handler.php',
            'signature-handler'
        );
        
        // Register SignatureHandler as singleton
        $this->app->singleton(SignatureHandler::class, function ($app) {
            return new SignatureHandler();
        });
    }
    
    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/signature-handler.php' => config_path('signature-handler.php'),
        ], 'signature-handler-config');
        
        // Publish frontend assets (Vue components)
        $this->publishes([
            __DIR__.'/../stubs/resources/js/pages/form-flow/signature' => resource_path('js/pages/form-flow/signature'),
        ], 'signature-handler-stubs');
        
        // Auto-register handler with form-flow-manager
        $this->registerHandler();
    }
    
    /**
     * Register the signature handler with form-flow-manager
     */
    protected function registerHandler(): void
    {
        // Get current handlers from config
        $handlers = config('form-flow.handlers', []);
        
        // Add signature handler
        $handlers['signature'] = SignatureHandler::class;
        
        // Update config
        config(['form-flow.handlers' => $handlers]);
    }
}
