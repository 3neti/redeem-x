<?php

namespace LBHurtado\FormHandlerSignature\Tests;

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
            \LBHurtado\FormHandlerSignature\SignatureHandlerServiceProvider::class,
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
        
        // Signature handler configuration
        $app['config']->set('signature-handler.width', 600);
        $app['config']->set('signature-handler.height', 256);
        $app['config']->set('signature-handler.quality', 0.85);
        $app['config']->set('signature-handler.format', 'image/png');
        $app['config']->set('signature-handler.line_width', 2);
        $app['config']->set('signature-handler.line_color', '#000000');
        $app['config']->set('signature-handler.line_cap', 'round');
        $app['config']->set('signature-handler.line_join', 'round');
    }
}
