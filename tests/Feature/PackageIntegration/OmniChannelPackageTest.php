<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use LBHurtado\OmniChannel\Contracts\SMSHandlerInterface;
use LBHurtado\OmniChannel\Data\SMSData;
use LBHurtado\OmniChannel\Events\SMSArrived;
use LBHurtado\OmniChannel\Models\SMS;
use LBHurtado\OmniChannel\Services\OmniChannelService;

uses(RefreshDatabase::class);

test('omnichannel package is loaded and autoloaded', function () {
    expect(class_exists(SMS::class))->toBeTrue()
        ->and(class_exists(SMSData::class))->toBeTrue()
        ->and(class_exists(SMSArrived::class))->toBeTrue()
        ->and(interface_exists(SMSHandlerInterface::class))->toBeTrue()
        ->and(class_exists(OmniChannelService::class))->toBeTrue();
});

test('sms model can be instantiated', function () {
    $sms = new SMS;

    expect($sms)->toBeInstanceOf(SMS::class);
});

test('sms model can be created', function () {
    $sms = SMS::create([
        'from' => '639171234567',
        'to' => '09187654321',
        'message' => 'Test SMS message',
    ]);

    expect($sms->exists)->toBeTrue()
        ->and($sms->from)->toBe('639171234567')
        ->and($sms->to)->toBe('09187654321')
        ->and($sms->message)->toBe('Test SMS message');
});

test('sms model has fillable properties', function () {
    $sms = new SMS;

    expect($sms->getFillable())->toBe(['from', 'to', 'message']);
});

test('sms data can be instantiated', function () {
    $smsData = new SMSData(
        from: '639171234567',
        to: '09187654321',
        message: 'Test message'
    );

    expect($smsData->from)->toBe('639171234567')
        ->and($smsData->to)->toBe('09187654321')
        ->and($smsData->message)->toBe('Test message');
});

test('sms data has validation rules', function () {
    $rules = SMSData::rules();

    expect($rules)->toHaveKey('from')
        ->and($rules)->toHaveKey('to')
        ->and($rules)->toHaveKey('message');
});

test('sms can be created from sms data', function () {
    $smsData = new SMSData(
        from: '639171234567',
        to: '09187654321',
        message: 'Test from data'
    );

    $sms = SMS::createFromSMSData($smsData);

    expect($sms->exists)->toBeTrue()
        ->and($sms->from)->toBe('639171234567')
        ->and($sms->message)->toBe('Test from data');
});

test('sms arrived event can be instantiated', function () {
    $smsData = new SMSData(
        from: '639171234567',
        to: '09187654321',
        message: 'Event test'
    );

    $event = new SMSArrived($smsData);

    expect($event->data)->toBeInstanceOf(SMSData::class)
        ->and($event->data->from)->toBe('639171234567');
});

test('sms arrived event can be dispatched', function () {
    Event::fake();

    $smsData = new SMSData(
        from: '639171234567',
        to: '09187654321',
        message: 'Event dispatch test'
    );

    event(new SMSArrived($smsData));

    Event::assertDispatched(SMSArrived::class);
});

test('omnichannel service can be instantiated', function () {
    $service = new OmniChannelService(
        url: 'https://example.com/sms',
        accessKey: 'test-key'
    );

    expect($service)->toBeInstanceOf(OmniChannelService::class);
});

test('omnichannel service can send sms via mock', function () {
    Http::fake([
        '*' => Http::response('ACK|Message sent successfully', 200),
    ]);

    $service = new OmniChannelService(
        url: 'https://example.com/sms',
        accessKey: 'test-key'
    );

    $result = $service->send('639171234567', 'Test message');

    expect($result)->toBeTrue();
});

test('omnichannel service handles send failure', function () {
    Http::fake([
        '*' => Http::response('ERROR|Failed to send', 500),
    ]);

    $service = new OmniChannelService(
        url: 'https://example.com/sms',
        accessKey: 'test-key'
    );

    $result = $service->send('639171234567', 'Test message');

    expect($result)->toBeFalse();
});

test('sms handler interface defines invoke method', function () {
    $reflection = new ReflectionClass(SMSHandlerInterface::class);
    $methods = $reflection->getMethods();
    $methodNames = array_map(fn ($m) => $m->getName(), $methods);

    expect($methodNames)->toContain('__invoke');
});

test('sms table exists in database', function () {
    expect(\Schema::hasTable('sms'))->toBeTrue();
});

test('sms table has required columns', function () {
    $columns = \Schema::getColumnListing('sms');

    expect(in_array('id', $columns))->toBeTrue()
        ->and(in_array('from', $columns))->toBeTrue()
        ->and(in_array('to', $columns))->toBeTrue()
        ->and(in_array('message', $columns))->toBeTrue();
});
