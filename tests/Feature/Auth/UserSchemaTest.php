<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Phase 1 TDD — User Schema Tests
|--------------------------------------------------------------------------
| Tests for the new columns on the users table.
| Written BEFORE migrations — these drive the implementation.
*/

it('can create a user without workos_id', function () {
    $user = User::factory()->create([
        'workos_id' => null,
    ]);

    expect($user->exists)->toBeTrue();
    expect($user->workos_id)->toBeNull();
});

it('can create a user with a password field', function () {
    $user = User::factory()->create([
        'password' => 'secret123',
    ]);

    expect($user->exists)->toBeTrue();
    expect(Hash::check('secret123', $user->password))->toBeTrue();
});

it('can create a user with a mobile field on the users table', function () {
    $user = User::factory()->create([
        'mobile' => '09171234567',
    ]);

    $fresh = User::find($user->id);
    expect($fresh->getRawOriginal('mobile'))->toBe('09171234567');
});

it('defaults auth_source to local for new users', function () {
    $user = User::factory()->create([
        'workos_id' => null,
    ]);

    expect($user->fresh()->auth_source)->toBe('local');
});

it('defaults status to active for new users', function () {
    $user = User::factory()->create();

    expect($user->fresh()->status)->toBe('active');
});

it('casts mobile_verified_at as datetime', function () {
    $user = User::factory()->create([
        'mobile_verified_at' => now(),
    ]);

    expect($user->mobile_verified_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('casts last_login_at as datetime', function () {
    $user = User::factory()->create([
        'last_login_at' => now(),
    ]);

    expect($user->last_login_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('automatically hashes the password', function () {
    $user = User::factory()->create([
        'password' => 'plaintext',
    ]);

    expect($user->password)->not->toBe('plaintext');
    expect(Hash::check('plaintext', $user->password))->toBeTrue();
});
