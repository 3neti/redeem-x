<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use LBHurtado\FormFlowManager\Services\DriverRegistry;
use LBHurtado\FormFlowManager\Services\FormFlowService;

/**
 * Form Flow Manager Service Provider
 * 
 * Registers routes, services, and configuration for the form flow system.
 */
class FormFlowServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Register DriverRegistry as singleton
        $this->app->singleton(DriverRegistry::class, function ($app) {
            return new DriverRegistry();
        });
        
        // Register FormFlowService
        $this->app->singleton(FormFlowService::class, function ($app) {
            return new FormFlowService();
        });
        
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/form-flow.php', 'form-flow'
        );
    }
    
    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Register routes
        $this->registerRoutes();
        
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/form-flow.php' => config_path('form-flow.php'),
        ], 'form-flow-config');
        
        // Publish driver directory
        $this->publishes([
            __DIR__.'/../config/form-flow-drivers' => config_path('form-flow-drivers'),
        ], 'form-flow-drivers');
        
        // Publish Vue components
        $this->publishes([
            __DIR__.'/../stubs/resources/js/pages/FormFlow' => resource_path('js/pages/FormFlow'),
        ], 'form-flow-views');
        
        // Auto-discover drivers
        if ($this->app->runningInConsole() === false) {
            $this->app->make(DriverRegistry::class)->discover();
        }
    }
    
    /**
     * Register package routes
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('form-flow.route_prefix', 'form-flow'),
            'middleware' => config('form-flow.middleware', ['web']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/form-flow.php');
        });
    }
}
