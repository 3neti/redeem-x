<?php

namespace LBHurtado\FormFlowManager\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            \Spatie\LaravelData\LaravelDataServiceProvider::class,
            \Inertia\ServiceProvider::class,
            \LBHurtado\FormFlowManager\FormFlowServiceProvider::class,
            \LBHurtado\FormHandlerLocation\LocationHandlerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        
        // Laravel Data configuration
        $app['config']->set('data.validation_strategy', 'only_requests');
        $app['config']->set('data.max_transformation_depth', 6);
        $app['config']->set('data.throw_when_max_transformation_depth_reached', 6);
        $app['config']->set('data.normalizers', [
            \Spatie\LaravelData\Normalizers\ModelNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
            \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
            \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
        ]);
        $app['config']->set('data.date_format', "Y-m-d\TH:i:sP");
        
        // Location handler configuration
        $app['config']->set('location-handler.opencage_api_key', 'test_key');
        $app['config']->set('location-handler.map_provider', 'google');
        $app['config']->set('location-handler.capture_snapshot', true);
        $app['config']->set('location-handler.require_address', false);
        
        // Register location handler
        $app['config']->set('form-flow.handlers.location', \LBHurtado\FormHandlerLocation\LocationHandler::class);
    }
}
