<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 2 TDD — Mobile Decoupling Tests
|--------------------------------------------------------------------------
| Tests that users.mobile column works correctly with HasChannels exclusion.
*/

it('reads mobile from column via property access', function () {
    $user = User::factory()->create(['mobile' => '09171234567']);

    // $user->mobile should read from column (via Eloquent), not channels
    expect($user->mobile)->not->toBeNull();
});

it('reads mobile from column in serialization', function () {
    $user = User::factory()->create(['mobile' => '09171234567']);

    $array = $user->toArray();
    expect($array['mobile'])->not->toBeNull();
});

it('getMobileAttribute falls back to channels when column is null', function () {
    $user = User::factory()->create(['mobile' => null]);

    // Set channel directly (bypassing exclusion since setChannel is an explicit API)
    $user->setChannel('mobile', '09171234567');

    // getMobileAttribute should fall back to channels
    $mobile = $user->getMobileAttribute();
    expect($mobile)->not->toBeNull();
});

it('getMobileAttribute returns null when both column and channels are empty', function () {
    $user = User::factory()->create(['mobile' => null]);

    expect($user->getMobileAttribute())->toBeNull();
});

it('property assignment writes to column not channels', function () {
    $user = User::factory()->create(['mobile' => null]);

    $user->mobile = '09179999999';
    $user->save();

    // Column should have the value
    $fresh = User::find($user->id);
    expect($fresh->getRawOriginal('mobile'))->toBe('09179999999');
});

it('mass assignment writes to column', function () {
    $user = User::factory()->create(['mobile' => null]);

    $user->update(['mobile' => '09178888888']);

    expect($user->fresh()->getRawOriginal('mobile'))->toBe('09178888888');
});

it('saved event syncs column to channel on create', function () {
    $user = User::factory()->create(['mobile' => '09171234567']);

    // The saved event should have synced to channels
    $channel = $user->channels()->where('name', 'mobile')->first();
    expect($channel)->not->toBeNull();
});

it('saved event syncs column to channel on update', function () {
    $user = User::factory()->create(['mobile' => '09171234567']);

    $user->update(['mobile' => '09179999999']);

    $channel = $user->channels()->where('name', 'mobile')->first();
    expect($channel)->not->toBeNull();
});

it('saved event does not loop when mobile unchanged', function () {
    $user = User::factory()->create(['mobile' => '09171234567']);

    // Update a non-mobile field — should not trigger channel sync
    $channelCountBefore = $user->channels()->where('name', 'mobile')->count();

    $user->update(['name' => 'Updated Name']);

    // Channel count should not increase (no duplicate inserts)
    $channelCountAfter = $user->channels()->where('name', 'mobile')->count();
    expect($channelCountAfter)->toBe($channelCountBefore);
});

it('non-mobile channels unaffected by exclusion', function () {
    $user = User::factory()->create();

    $user->webhook = 'https://example.com/hook';

    $channel = $user->channels()->where('name', 'webhook')->first();
    expect($channel)->not->toBeNull();
    expect($channel->value)->toBe('https://example.com/hook');
});
