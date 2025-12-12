<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerLocation;

use Illuminate\Support\ServiceProvider;
use LBHurtado\FormFlowManager\Services\DriverRegistry;

/**
 * Location Handler Service Provider
 * 
 * Registers the location handler with the form flow system.
 */
class LocationHandlerServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/location-handler.php',
            'location-handler'
        );
        
        // Register LocationHandler as singleton
        $this->app->singleton(LocationHandler::class, function ($app) {
            return new LocationHandler();
        });
    }
    
    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Register test routes (only in local/testing)
        if (!$this->app->isProduction()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/test.php');
        }
        
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/location-handler.php' => config_path('location-handler.php'),
        ], 'location-handler-config');
        
        // Publish frontend assets (Vue components, composables)
        $this->publishes([
            __DIR__.'/../stubs/resources/js/FormHandlerLocation' => resource_path('js/FormHandlerLocation'),
        ], 'location-handler-stubs');
        
        // Auto-register handler with form-flow-manager
        $this->registerHandler();
        
        // Register Inertia view namespace
        if (class_exists(\Inertia\Inertia::class)) {
            \Inertia\Inertia::share([
                'form_handler_location_path' => resource_path('js/vendor/form-handler-location'),
            ]);
        }
    }
    
    /**
     * Register the location handler with form-flow-manager
     */
    protected function registerHandler(): void
    {
        // Get current handlers from config
        $handlers = config('form-flow.handlers', []);
        
        // Add location handler
        $handlers['location'] = LocationHandler::class;
        
        // Update config
        config(['form-flow.handlers' => $handlers]);
    }
}
