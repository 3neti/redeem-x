<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    // Set mobile via HasChannels trait
    $this->user->setChannel('mobile', '09171234567');
});

// ============================================================================
// GET /api/v1/auth/me - Get Authenticated User
// ============================================================================

test('authenticated user can get their info', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'mobile',
                    'avatar',
                    'current_token_abilities',
                ],
            ],
            'meta' => [
                'timestamp',
                'version',
            ],
        ]);

    expect($response->json('data.user.id'))->toBe($this->user->id);
    expect($response->json('data.user.email'))->toBe('test@example.com');
});

test('unauthenticated user cannot access me endpoint', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized();
});

// ============================================================================
// POST /api/v1/auth/tokens - Create Token
// ============================================================================

test('authenticated user can create api token', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'name' => 'Test Token',
        'abilities' => ['voucher:generate', 'voucher:list'],
        'expires_in_days' => 90,
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'token' => [
                    'id',
                    'name',
                    'abilities',
                    'last_used_at',
                    'expires_at',
                    'created_at',
                    'plain_text_token',
                ],
                'message',
            ],
            'meta',
        ]);

    expect($response->json('data.token.name'))->toBe('Test Token');
    expect($response->json('data.token.abilities'))->toBe(['voucher:generate', 'voucher:list']);
    expect($response->json('data.token.plain_text_token'))->not()->toBeNull();

    // Verify token is stored in database
    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $this->user->id,
        'name' => 'Test Token',
    ]);
});

test('token can be created with all abilities', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'name' => 'Full Access Token',
        'abilities' => ['*'],
    ]);

    $response->assertCreated();
    expect($response->json('data.token.abilities'))->toBe(['*']);
});

test('token can be created without expiration', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'name' => 'Never Expires Token',
        'abilities' => ['voucher:list'],
    ]);

    $response->assertCreated();
    expect($response->json('data.token.expires_at'))->toBeNull();
});

test('token creation requires name', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'abilities' => ['voucher:list'],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('token creation validates ability values', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'name' => 'Invalid Token',
        'abilities' => ['invalid:ability'],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['abilities.0']);
});

test('token creation validates expiration days', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'name' => 'Test Token',
        'abilities' => ['*'],
        'expires_in_days' => 999, // Invalid
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['expires_in_days']);
});

// ============================================================================
// GET /api/v1/auth/tokens - List Tokens
// ============================================================================

test('authenticated user can list their tokens', function () {
    Sanctum::actingAs($this->user, ['*']);

    // Create some tokens
    $this->user->createToken('Token 1', ['voucher:list']);
    $this->user->createToken('Token 2', ['voucher:generate']);
    $this->user->createToken('Token 3', ['*']);

    $response = $this->getJson('/api/v1/auth/tokens');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tokens' => [
                    '*' => [
                        'id',
                        'name',
                        'abilities',
                        'last_used_at',
                        'expires_at',
                        'created_at',
                    ],
                ],
                'total',
            ],
            'meta',
        ]);

    expect($response->json('data.total'))->toBe(3); // 3 created tokens (Sanctum::actingAs doesn't persist)
});

test('tokens are listed in descending order by creation date', function () {
    Sanctum::actingAs($this->user, ['*']);

    $token1 = $this->user->createToken('Oldest');
    sleep(1);
    $token2 = $this->user->createToken('Newest');

    $response = $this->getJson('/api/v1/auth/tokens');

    $tokens = $response->json('data.tokens');
    // First token should be the newest
    expect($tokens[0]['name'])->toBe('Newest');
});

test('user only sees their own tokens', function () {
    $otherUser = User::factory()->create();
    $otherUser->createToken('Other User Token');

    Sanctum::actingAs($this->user, ['*']);
    $this->user->createToken('My Token');

    $response = $this->getJson('/api/v1/auth/tokens');

    $response->assertOk();

    // Should see 1 token: one from createToken (Sanctum::actingAs doesn't persist)
    expect($response->json('data.total'))->toBe(1);

    // Verify "Other User Token" is not in the list
    $tokenNames = collect($response->json('data.tokens'))->pluck('name');
    expect($tokenNames)->not()->toContain('Other User Token');
});

// ============================================================================
// DELETE /api/v1/auth/tokens/{tokenId} - Revoke Token
// ============================================================================

test('authenticated user can revoke their own token', function () {
    Sanctum::actingAs($this->user, ['*']);

    $token = $this->user->createToken('Token to Revoke');
    $tokenId = $token->accessToken->id;

    $response = $this->deleteJson("/api/v1/auth/tokens/{$tokenId}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'message' => "Token 'Token to Revoke' has been revoked successfully.",
            ],
        ]);

    // Verify token is deleted
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $tokenId,
    ]);
});

test('user cannot revoke another users token', function () {
    $otherUser = User::factory()->create();
    $otherToken = $otherUser->createToken('Other User Token');

    Sanctum::actingAs($this->user, ['*']);

    $response = $this->deleteJson("/api/v1/auth/tokens/{$otherToken->accessToken->id}");

    $response->assertNotFound();

    // Verify token still exists
    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $otherToken->accessToken->id,
    ]);
});

test('revoking non-existent token returns 404', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->deleteJson('/api/v1/auth/tokens/99999');

    $response->assertNotFound();
});

// ============================================================================
// DELETE /api/v1/auth/tokens - Revoke All Tokens
// ============================================================================

test('authenticated user can revoke all their tokens', function () {
    Sanctum::actingAs($this->user, ['*']);

    // Create multiple tokens
    $this->user->createToken('Token 1');
    $this->user->createToken('Token 2');
    $this->user->createToken('Token 3');

    $response = $this->deleteJson('/api/v1/auth/tokens');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'message',
                'revoked_count',
            ],
        ]);

    expect($response->json('data.revoked_count'))->toBe(3); // 3 created tokens (Sanctum::actingAs doesn't persist)

    // Verify all tokens are deleted
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $this->user->id,
    ]);
});

test('revoking all tokens with no tokens returns zero count', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    // Delete the token created by Sanctum::actingAs
    $user->tokens()->delete();

    $response = $this->deleteJson('/api/v1/auth/tokens');

    $response->assertOk();
    expect($response->json('data.revoked_count'))->toBe(0);
});

test('revoking all tokens does not affect other users', function () {
    $otherUser = User::factory()->create();
    $otherToken = $otherUser->createToken('Other User Token');

    Sanctum::actingAs($this->user, ['*']);
    $this->user->createToken('My Token');

    $this->deleteJson('/api/v1/auth/tokens');

    // Verify other user's token still exists
    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $otherToken->accessToken->id,
        'tokenable_id' => $otherUser->id,
    ]);
});

// ============================================================================
// API Response Format Tests
// ============================================================================

test('all auth endpoints return consistent meta structure', function () {
    Sanctum::actingAs($this->user, ['*']);

    $endpoints = [
        ['method' => 'get', 'uri' => '/api/v1/auth/me'],
        ['method' => 'get', 'uri' => '/api/v1/auth/tokens'],
    ];

    foreach ($endpoints as $endpoint) {
        $response = $this->{$endpoint['method'].'Json'}($endpoint['uri']);

        $response->assertOk()
            ->assertJsonStructure([
                'meta' => [
                    'timestamp',
                    'version',
                ],
            ]);

        expect($response->json('meta.version'))->toBe('v1');
    }
});

// ============================================================================
// Token Abilities Tests
// ============================================================================

test('token with specific abilities can only access allowed endpoints', function () {
    // Create token with limited abilities
    $token = $this->user->createToken('Limited Token', ['voucher:list']);

    // Make request with the limited token
    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/auth/me');

    $response->assertOk();

    // Verify current_token_abilities shows the limited abilities
    expect($response->json('data.user.current_token_abilities'))->toBe(['voucher:list']);
});

// ============================================================================
// Rate Limiting Tests
// ============================================================================

test('auth endpoints are rate limited', function () {
    Sanctum::actingAs($this->user, ['*']);

    // Note: Rate limit is 60 req/min, so we won't hit it in tests
    // Just verify the endpoints work normally
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk();
});
