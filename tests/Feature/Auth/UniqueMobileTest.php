<?php

declare(strict_types=1);

use App\Models\User;
use App\Rules\UniqueMobile;

/*
|--------------------------------------------------------------------------
| UniqueMobile Rule Tests
|--------------------------------------------------------------------------
| Covers both registration (no ignore) and profile update (ignore self).
*/

beforeEach(function () {
    $this->withoutVite();
    config([
        'auth_modes.enable_registration' => true,
        'auth_modes.enable_local_login' => true,
    ]);
});

// ── Registration (no ignoreUserId) ─────────────────────────────────────

it('blocks registration when mobile matches exactly', function () {
    User::factory()->create(['mobile' => '09171234567']);

    $this->post('/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'mobile' => '09171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('mobile');
});

it('blocks registration when mobile matches in E.164 format', function () {
    User::factory()->create(['mobile' => '09171234567']);

    $this->post('/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'mobile' => '+639171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('mobile');
});

it('blocks registration when mobile matches in stripped E.164 format', function () {
    User::factory()->create(['mobile' => '09171234567']);

    $this->post('/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'mobile' => '639171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('mobile');
});

it('allows registration with a genuinely different mobile', function () {
    User::factory()->create(['mobile' => '09171234567']);

    $this->post('/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'mobile' => '09181234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasNoErrors();
});

// ── Profile update (ignoreUserId = self) ───────────────────────────────

it('allows user to keep their own mobile on profile update', function () {
    $user = User::factory()->create([
        'mobile' => '09171234567',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Updated Name',
            'mobile' => '09171234567',
        ])
        ->assertSessionHasNoErrors();
});

it('allows user to keep their own mobile in E.164 format on profile update', function () {
    $user = User::factory()->create([
        'mobile' => '09171234567',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Updated Name',
            'mobile' => '+639171234567',
        ])
        ->assertSessionHasNoErrors();
});

it('blocks profile update when mobile belongs to another user', function () {
    User::factory()->create(['mobile' => '09171234567']);
    $user = User::factory()->create([
        'mobile' => '09181234567',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Updated Name',
            'mobile' => '09171234567',
        ])
        ->assertSessionHasErrors('mobile');
});

it('blocks profile update when mobile matches another user in E.164 format', function () {
    User::factory()->create(['mobile' => '09171234567']);
    $user = User::factory()->create([
        'mobile' => '09181234567',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Updated Name',
            'mobile' => '+639171234567',
        ])
        ->assertSessionHasErrors('mobile');
});

it('allows profile update to a genuinely different mobile', function () {
    User::factory()->create(['mobile' => '09171234567']);
    $user = User::factory()->create([
        'mobile' => '09181234567',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Updated Name',
            'mobile' => '09191234567',
        ])
        ->assertSessionHasNoErrors();
});

// ── Unit-level: Rule class directly ────────────────────────────────────

it('rule passes for unique mobile', function () {
    $rule = new UniqueMobile();
    $failed = false;

    $rule->validate('mobile', '09171234567', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('rule fails when mobile exists in DB', function () {
    User::factory()->create(['mobile' => '09171234567']);

    $rule = new UniqueMobile();
    $failed = false;

    $rule->validate('mobile', '+639171234567', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('rule passes when ignoring own user ID', function () {
    $user = User::factory()->create(['mobile' => '09171234567']);

    $rule = new UniqueMobile($user->id);
    $failed = false;

    $rule->validate('mobile', '09171234567', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('rule fails when another user has mobile even with ignoreUserId set', function () {
    $existing = User::factory()->create(['mobile' => '09171234567']);
    $self = User::factory()->create(['mobile' => '09181234567']);

    $rule = new UniqueMobile($self->id);
    $failed = false;

    $rule->validate('mobile', '09171234567', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});
