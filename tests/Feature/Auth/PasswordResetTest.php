<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| Phase 3 TDD — Password Reset Tests
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->withoutVite();
    config([
        'auth_modes.enable_local_login' => true,
        'auth_modes.enable_password_login' => true,
    ]);
});

it('sends a password reset link for a valid email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('oldpassword'),
    ]);

    $this->post('/forgot-password', [
        'email' => 'user@example.com',
    ])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('returns validation error for invalid email', function () {
    $this->post('/forgot-password', [
        'email' => 'nonexistent@example.com',
    ])->assertSessionHasErrors('email');
});

it('resets password with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('oldpassword'),
    ]);

    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertRedirect();

    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

it('fails with an invalid token', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('oldpassword'),
    ]);

    $this->post('/reset-password', [
        'token' => 'invalid-token',
        'email' => 'user@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});

it('can login with new password after reset', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('oldpassword'),
    ]);

    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $this->post('/login', [
        'login' => 'user@example.com',
        'password' => 'newpassword123',
    ]);

    $this->assertAuthenticatedAs($user);
});

it('shows forgot password page', function () {
    $this->get('/forgot-password')->assertStatus(200);
});

it('shows reset password page with valid token', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $token = Password::createToken($user);

    $this->get("/reset-password/{$token}?email=user@example.com")->assertStatus(200);
});
