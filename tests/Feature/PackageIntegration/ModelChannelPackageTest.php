<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\ModelChannel\Enums\Channel as ChannelEnum;
use LBHurtado\ModelChannel\Models\Channel;

uses(RefreshDatabase::class);

test('model channel package is loaded and autoloaded', function () {
    expect(class_exists(Channel::class))->toBeTrue()
        ->and(enum_exists(ChannelEnum::class))->toBeTrue()
        ->and(trait_exists(\LBHurtado\ModelChannel\Traits\HasChannels::class))->toBeTrue();
});

test('user has channels trait', function () {
    $user = User::factory()->create();

    expect(method_exists($user, 'channels'))->toBeTrue()
        ->and(method_exists($user, 'setChannel'))->toBeTrue()
        ->and(method_exists($user, 'isValidChannel'))->toBeTrue();
});

test('user can have channels relationship', function () {
    $user = User::factory()->create();

    $user->channels()->create(['name' => 'mobile', 'value' => '639171234567']);
    $user->channels()->create(['name' => 'webhook', 'value' => 'https://example.com']);

    expect($user->channels()->count())->toBe(2)
        ->and($user->channels->pluck('name')->toArray())->toContain('mobile', 'webhook');
});

test('user can set mobile channel', function () {
    $user = User::factory()->create();

    $user->setChannel('mobile', '09171234567');

    expect($user->channels()->where('name', 'mobile')->exists())->toBeTrue();

    // Verify mobile is normalized to E.164 format without +
    $channel = $user->channels()->where('name', 'mobile')->first();
    expect($channel->value)->toBe('639171234567');
});

test('user can set webhook channel', function () {
    $user = User::factory()->create();

    $user->setChannel('webhook', 'https://example.com/webhook');

    $channel = $user->channels()->where('name', 'webhook')->first();
    expect($channel)->not->toBeNull()
        ->and($channel->value)->toBe('https://example.com/webhook');
});

test('user can set channel using enum', function () {
    $user = User::factory()->create();

    $user->setChannel(ChannelEnum::MOBILE, '09171234567');

    expect($user->channels()->where('name', ChannelEnum::MOBILE->value)->exists())->toBeTrue();
});

test('user can access channel via magic property', function () {
    $user = User::factory()->create();

    $user->channels()->create(['name' => 'mobile', 'value' => '09171234567']);
    $user->channels()->create(['name' => 'webhook', 'value' => 'https://example.com']);

    expect($user->mobile)->toBe('09171234567')
        ->and($user->webhook)->toBe('https://example.com');
});

test('user can set channel via magic property', function () {
    $user = User::factory()->create();

    $user->mobile = '09171234567';
    $user->webhook = 'https://example.com/hook';

    $mobileChannel = $user->channels()->where('name', 'mobile')->first();
    $webhookChannel = $user->channels()->where('name', 'webhook')->first();

    expect($mobileChannel->value)->toBe('639171234567')
        ->and($webhookChannel->value)->toBe('https://example.com/hook');
});

test('validates channel names against enum', function () {
    $user = User::factory()->create();

    expect($user->isValidChannel('mobile', '09171234567'))->toBeTrue()
        ->and($user->isValidChannel('webhook', 'https://example.com'))->toBeTrue()
        ->and($user->isValidChannel('email', 'test@example.com'))->toBeFalse(); // Not in enum
});

test('validates channel values using rules', function () {
    $user = User::factory()->create();

    expect($user->isValidChannel('mobile', '09171234567'))->toBeTrue()
        ->and($user->isValidChannel('mobile', 'invalid'))->toBeFalse()
        ->and($user->isValidChannel('webhook', 'https://example.com'))->toBeTrue()
        ->and($user->isValidChannel('webhook', 'not-a-url'))->toBeFalse();
});

test('throws exception for invalid channel', function () {
    $user = User::factory()->create();

    expect(fn () => $user->setChannel('invalid_channel', 'value'))
        ->toThrow(Exception::class, 'Channel name is not valid');
});

test('user can be found by mobile channel', function () {
    $user = User::factory()->create();
    $user->setChannel('mobile', '09171234567');

    $found = User::findByChannel('mobile', '09171234567');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($user->id);
});

test('user can be found by webhook channel', function () {
    $user = User::factory()->create();
    $user->setChannel('webhook', 'https://example.com/webhook');

    $found = User::findByChannel('webhook', 'https://example.com/webhook');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($user->id);
});

test('user can be found using dynamic finder', function () {
    $user = User::factory()->create();
    $user->setChannel('mobile', '09171234567');

    $found = User::findByMobile('09171234567');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($user->id);
});

test('channels table exists in database', function () {
    expect(\Schema::hasTable('channels'))->toBeTrue();
});

test('channels table has required columns', function () {
    $columns = \Schema::getColumnListing('channels');

    expect(in_array('id', $columns))->toBeTrue()
        ->and(in_array('name', $columns))->toBeTrue()
        ->and(in_array('value', $columns))->toBeTrue()
        ->and(in_array('model_type', $columns))->toBeTrue()
        ->and(in_array('model_id', $columns))->toBeTrue();
});
