<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 3 TDD — Registration Tests
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->withoutVite();
    config([
        'auth_modes.enable_registration' => true,
        'auth_modes.enable_local_login' => true,
    ]);
});

it('registers a user with valid data and logs in', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'mobile' => '09171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->auth_source)->toBe('local');
    expect($user->getRawOriginal('mobile'))->toBe('+639171234567');
});

it('hashes the password on registration', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'mobile' => '09171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'test@example.com')->first();
    expect($user->password)->not->toBe('password123');
    expect(\Illuminate\Support\Facades\Hash::check('password123', $user->password))->toBeTrue();
});

it('fails registration without name', function () {
    $this->post('/register', [
        'email' => 'test@example.com',
        'mobile' => '09171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('name');
});

it('fails registration without email', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'mobile' => '09171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');
});

it('fails registration without mobile', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('mobile');
});

it('fails registration without password', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'mobile' => '09171234567',
    ])->assertSessionHasErrors('password');
});

it('fails registration with duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'existing@example.com',
        'mobile' => '09171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');
});

it('fails registration with duplicate mobile (same format)', function () {
    User::factory()->create(['mobile' => '09171234567']);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'new@example.com',
        'mobile' => '09171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('mobile');
});

it('fails registration with duplicate mobile (E.164 vs national)', function () {
    User::factory()->create(['mobile' => '09171234567']);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'new@example.com',
        'mobile' => '+639171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('mobile');
});

it('fails registration with duplicate mobile (stripped E.164 vs national)', function () {
    User::factory()->create(['mobile' => '09171234567']);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'new@example.com',
        'mobile' => '639171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('mobile');
});

it('fails registration when registration is disabled', function () {
    config(['auth_modes.enable_registration' => false]);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'mobile' => '09171234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertNotFound();
});

it('shows registration page when enabled', function () {
    $this->get('/register')->assertStatus(200);
});

it('returns 404 for registration page when disabled', function () {
    config(['auth_modes.enable_registration' => false]);

    $this->get('/register')->assertNotFound();
});
