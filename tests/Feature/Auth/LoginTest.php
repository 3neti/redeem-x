<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 3 TDD — Login Tests
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->withoutVite();
    config([
        'auth_modes.enable_local_login' => true,
        'auth_modes.enable_password_login' => true,
        'auth_modes.enable_mobile_login' => true,
    ]);
});

it('authenticates with valid email and password', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('password123'),
        'auth_source' => 'local',
    ]);

    $response = $this->post('/login', [
        'login' => 'user@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('authenticates with valid mobile and password', function () {
    $user = User::factory()->create([
        'mobile' => '09171234567',
        'password' => bcrypt('password123'),
        'auth_source' => 'local',
    ]);

    $response = $this->post('/login', [
        'login' => '09171234567',
        'password' => 'password123',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('fails login with wrong password', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('password123'),
    ]);

    $this->post('/login', [
        'login' => 'user@example.com',
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors();

    $this->assertGuest();
});

it('fails login with non-existent email', function () {
    $this->post('/login', [
        'login' => 'nobody@example.com',
        'password' => 'password123',
    ])->assertSessionHasErrors();

    $this->assertGuest();
});

it('fails login with non-existent mobile', function () {
    $this->post('/login', [
        'login' => '09999999999',
        'password' => 'password123',
    ])->assertSessionHasErrors();

    $this->assertGuest();
});

it('updates last_login_at on successful login', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('password123'),
        'last_login_at' => null,
    ]);

    $this->post('/login', [
        'login' => 'user@example.com',
        'password' => 'password123',
    ]);

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();
});

it('returns 404 when local login is disabled', function () {
    config(['auth_modes.enable_local_login' => false]);

    $this->post('/login', [
        'login' => 'user@example.com',
        'password' => 'password123',
    ])->assertNotFound();
});

it('shows login page when local login is enabled', function () {
    $this->get('/login')->assertStatus(200);
});
