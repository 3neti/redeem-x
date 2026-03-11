<?php

declare(strict_types=1);

use App\Models\AuthIdentity;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 1 TDD — Auth Identity Tests
|--------------------------------------------------------------------------
| Tests for the auth_identities table and model.
*/

it('can create an auth identity linking a user to a provider', function () {
    $user = User::factory()->create(['workos_id' => null]);

    $identity = AuthIdentity::create([
        'user_id' => $user->id,
        'provider' => 'workos',
        'provider_user_id' => 'user_01ABC',
        'email' => $user->email,
    ]);

    expect($identity->exists)->toBeTrue();
    expect($identity->provider)->toBe('workos');
    expect($identity->user->id)->toBe($user->id);
});

it('allows a user to have multiple auth identities', function () {
    $user = User::factory()->create(['workos_id' => null]);

    AuthIdentity::create([
        'user_id' => $user->id,
        'provider' => 'workos',
        'provider_user_id' => 'user_01ABC',
        'email' => $user->email,
    ]);

    AuthIdentity::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google_123',
        'email' => $user->email,
    ]);

    expect($user->authIdentities()->count())->toBe(2);
});

it('can find a user by provider and provider_user_id', function () {
    $user = User::factory()->create(['workos_id' => null]);

    AuthIdentity::create([
        'user_id' => $user->id,
        'provider' => 'workos',
        'provider_user_id' => 'user_01XYZ',
        'email' => $user->email,
    ]);

    $found = AuthIdentity::where('provider', 'workos')
        ->where('provider_user_id', 'user_01XYZ')
        ->first();

    expect($found)->not->toBeNull();
    expect($found->user_id)->toBe($user->id);
});
