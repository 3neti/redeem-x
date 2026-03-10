<?php

namespace Tests;

use App\Http\Middleware\AdvancedRateLimiting;
use App\Settings\VoucherSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app)
    {
        // Disable settings data migrations during tests
        // The settings table will still be created by the regular migration
        // but data migrations won't run, avoiding race conditions
        $app['config']->set('settings.migrations_paths', []);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Disable rate limiting middleware so the full test suite
        // doesn't hit 429 Too Many Requests responses
        $this->withoutMiddleware([
            ThrottleRequests::class,
            AdvancedRateLimiting::class,
        ]);

        // Fake VoucherSettings with default values
        // This prevents tests from hitting the database for settings
        VoucherSettings::fake([
            'default_amount' => 50,
            'default_expiry_days' => null,
            'default_rider_url' => config('app.url'),
            'default_success_message' => 'Thank you for redeeming your voucher! The cash will be transferred shortly.',
        ]);
    }
}
