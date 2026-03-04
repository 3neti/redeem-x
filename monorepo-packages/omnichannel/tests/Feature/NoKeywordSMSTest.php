<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use LBHurtado\OmniChannel\Data\SMSData;
use LBHurtado\OmniChannel\Events\SMSArrived;
use LBHurtado\OmniChannel\Middlewares\AutoReplySMS;
use LBHurtado\OmniChannel\Middlewares\CleanSMS;
use LBHurtado\OmniChannel\Middlewares\LogSMS;
use LBHurtado\OmniChannel\Middlewares\RateLimitSMS;
use LBHurtado\OmniChannel\Middlewares\StoreSMS;
use LBHurtado\OmniChannel\Services\SMSRouterService;

uses(RefreshDatabase::class);

beforeEach(function () {
    // nothing special here; routes auto-loaded by your package provider
});

it('runs the full sms pipeline for a normal message', function () {
    // 1) Fake / spy on facades
    Log::spy();             // or Log::fake() on Laravel 10+
    Event::fake([SMSArrived::class]);
    Cache::flush();

    // 2) Register the catch‐all SMS route
    $router = $this->app->make(SMSRouterService::class);
    $router->register(
        '{message}',
        fn ($values, $from, $to) => response()->json(['message' => null]),
        [
            LogSMS::class,
            RateLimitSMS::class,
            CleanSMS::class,
            AutoReplySMS::class,
            StoreSMS::class,
            LogSMS::class,
        ]
    );

    // 3) Hit the endpoint
    $payload = [
        'from' => '09171234567',
        'to' => '22560537',
        'message' => '  hello   world  ',
    ];

    $response = $this->postJson('/sms', $payload);

    $response->assertStatus(200)
        ->assertExactJson(['message' => null]);

    // 4) Event was dispatched with cleaned data
    Event::assertDispatched(SMSArrived::class, fn ($e) => $e->data instanceof SMSData
        && $e->data->message === 'hello   world'
    );

    // 5) CleanSMS must have run:
    Log::shouldHaveReceived('info')->withArgs(function ($msg, $ctx) {
        return str_contains($msg, 'Running CleanSMS Middleware')
            && $ctx['message'] === 'hello world';
    });

    // 6) RateLimitSMS bumped cache
    $this->assertEquals(1, Cache::get('sms_rate_limit:09171234567'));

    // 7) StoreSMS wrote to the DB
    $this->assertDatabaseHas('sms', [
        'from' => $payload['from'],
        'to' => $payload['to'],
        'message' => 'hello world',
    ]);
});
