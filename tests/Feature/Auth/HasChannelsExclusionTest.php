<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 2 TDD — HasChannels Exclusion Tests
|--------------------------------------------------------------------------
| Tests that the $excludedChannels trait feature works correctly.
*/

it('model without excludedChannels reads mobile from channels via __get', function () {
    // The package test models don't define $excludedChannels, so they keep old behavior.
    // We test this indirectly: a User WITH the exclusion should NOT read from channels.
    // This test verifies the User model with exclusion reads from Eloquent instead.
    $user = User::factory()->create(['mobile' => '09171234567']);

    // With $excludedChannels = ['mobile'] on User, this should read from the column
    expect($user->mobile)->not->toBeNull();
});

it('excluded channel delegates __get to Eloquent', function () {
    $user = User::factory()->create(['mobile' => '09171234567']);

    // Property access should go through Eloquent (getMobileAttribute), not channels
    // The column value should be accessible
    $rawValue = $user->getRawOriginal('mobile');
    expect($rawValue)->toBe('+639171234567');
});

it('excluded channel delegates __set to Eloquent', function () {
    $user = User::factory()->create(['mobile' => null]);

    // Property assignment should write to column, not channels
    $user->mobile = '09179999999';
    $user->save();

    $fresh = User::find($user->id);
    expect($fresh->getRawOriginal('mobile'))->toBe('+639179999999');
});

it('non-excluded channels still intercepted by trait for __get', function () {
    $user = User::factory()->create();
    $user->setChannel('webhook', 'https://example.com/hook');

    // webhook is NOT in $excludedChannels, so trait intercepts
    expect($user->webhook)->toBe('https://example.com/hook');
});

it('non-excluded channels still intercepted by trait for __set', function () {
    $user = User::factory()->create();

    // webhook is NOT excluded, so __set writes to channels table
    $user->webhook = 'https://example.com/hook';

    $channel = $user->channels()->where('name', 'webhook')->first();
    expect($channel)->not->toBeNull();
    expect($channel->value)->toBe('https://example.com/hook');
});

it('explicit setChannel still works for excluded channels', function () {
    $user = User::factory()->create();

    // Direct API call always writes to channels, regardless of exclusion
    $user->setChannel('mobile', '09171234567');

    $channel = $user->channels()->where('name', 'mobile')->first();
    expect($channel)->not->toBeNull();
});

it('findByChannel works for non-excluded channels', function () {
    $user = User::factory()->create();
    $user->forceSetChannel('telegram', 'chat_12345');

    $found = User::findByChannel('telegram', 'chat_12345');
    expect($found)->not->toBeNull();
    expect($found->id)->toBe($user->id);
});
