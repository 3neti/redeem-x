<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class)->group('middleware');

it('allows users with mobile number to continue', function () {
    // Arrange: Create user with mobile number AND wallet balance
    $user = User::factory()->withMobile()->create();
    $user->depositFloat(100); // Satisfy wallet balance requirement

    // Act: Visit protected route
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert: Should reach the page (not redirect)
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('vouchers/generate/CreateV2'));
});

it('redirects users without mobile to profile', function () {
    // Arrange: Create user WITHOUT mobile number
    $user = User::factory()->create();
    // Don't set mobile channel

    // Act: Try to visit protected route
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert: Should redirect to profile with correct parameters
    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('profile')
        ->toContain('reason=mobile_required')
        ->toContain('return_to=');
});

it('includes flash message on redirect', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert: Flash message present
    $response->assertSessionHas('flash.type', 'warning');
    $response->assertSessionHas('flash.message', 'Please add your mobile number to continue.');
});

it('preserves full url including query params', function () {
    // Arrange
    $user = User::factory()->create();

    // Act: Visit with query parameters
    $response = actingAs($user)->get('/vouchers/generate?mode=simple&preset=standard');

    // Assert: return_to includes full URL with query params
    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('return_to=')
        ->toContain('mode%3Dsimple')
        ->toContain('preset%3Dstandard');
});

it('blocks bulk voucher generation without mobile', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $response = actingAs($user)->get('/vouchers/generate/bulk');

    // Assert
    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)
        ->toContain('profile')
        ->toContain('reason=mobile_required');
    // Check for URL-encoded path in return_to parameter
    expect(urldecode($location))->toContain('/vouchers/generate/bulk');
});

it('blocks topup without mobile', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $response = actingAs($user)->get('/topup');

    // Assert
    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)
        ->toContain('profile')
        ->toContain('reason=mobile_required');
    // Check for URL-encoded path in return_to parameter
    expect(urldecode($location))->toContain('/topup');
});

it('allows topup when mobile exists', function () {
    // Arrange
    $user = User::factory()->withMobile()->create();

    // Act
    $response = actingAs($user)->get('/topup');

    // Assert: Should reach topup page
    $response->assertStatus(200);
});

it('handles mobile in different formats', function () {
    // Arrange: User with mobile in E.164 format (as stored in DB)
    $user = User::factory()->withMobile('09171234567')->create();
    $user->depositFloat(50); // Satisfy wallet balance requirement

    // Act
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert: Should pass middleware (mobile exists in any format)
    $response->assertStatus(200);
});

it('does not block unauthenticated users', function () {
    // Act: Try to visit without authentication
    $response = get('/vouchers/generate');

    // Assert: Should redirect to login (auth middleware runs first)
    $response->assertRedirect('/login');
});
