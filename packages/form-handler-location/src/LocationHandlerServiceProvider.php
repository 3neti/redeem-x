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
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/location-handler.php' => config_path('location-handler.php'),
        ], 'location-handler-config');
        
        // Publish frontend assets (Vue components, composables)
        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/form-handler-location'),
        ], 'location-handler-assets');
        
        // Register with DriverRegistry if form-flow-manager is loaded
        if ($this->app->bound(DriverRegistry::class)) {
            $registry = $this->app->make(DriverRegistry::class);
            $handler = $this->app->make(LocationHandler::class);
            
            // Register the handler (the registry will handle duplicates)
            // Note: We're registering as a handler, not a driver
            // Drivers are YAML-based configs, handlers are PHP classes
        }
        
        // Register Inertia view namespace
        if (class_exists(\Inertia\Inertia::class)) {
            \Inertia\Inertia::share([
                'form_handler_location_path' => resource_path('js/vendor/form-handler-location'),
            ]);
        }
    }
}
