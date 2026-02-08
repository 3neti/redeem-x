<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->depositFloat(10000);
});

it('saves splash fields when generating via API endpoint', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/vouchers', [
            'amount' => 75,
            'count' => 1,
            'prefix' => 'API',
            'ttl_days' => 30,
            'rider_message' => 'Test API message',
            'rider_url' => 'https://api-test.com',
            'rider_redirect_timeout' => 12,
            'rider_splash' => '# API Test Splash\n\nThis is from the API endpoint.',
            'rider_splash_timeout' => 20,
        ]);

    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data)->toHaveKey('vouchers')
        ->and($data['vouchers'])->toHaveCount(1);

    $voucherCode = $data['vouchers'][0]['code'];
    $voucher = Voucher::where('code', $voucherCode)->first();

    expect($voucher)->not->toBeNull();

    $riderData = $voucher->instructions->rider->toArray();

    dump('API - Voucher code:', $voucherCode);
    dump('API - Rider data:', $riderData);

    expect($riderData)->toHaveKeys([
        'message',
        'url',
        'redirect_timeout',
        'splash',
        'splash_timeout',
    ])
        ->and($riderData['splash'])->toBe('# API Test Splash\n\nThis is from the API endpoint.')
        ->and($riderData['splash_timeout'])->toBe(20);
});

it('accepts null splash fields via API', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/vouchers', [
            'amount' => 50,
            'count' => 1,
            'rider_message' => 'Message only',
            'rider_url' => null,
            'rider_redirect_timeout' => null,
            'rider_splash' => null,
            'rider_splash_timeout' => null,
        ]);

    $response->assertStatus(201);

    $voucherCode = $response->json('data.vouchers.0.code');
    $voucher = Voucher::where('code', $voucherCode)->first();

    expect($voucher->instructions->rider->splash)->toBeNull()
        ->and($voucher->instructions->rider->splash_timeout)->toBeNull();
});

it('validates splash field max length via API', function () {
    $longContent = str_repeat('A', 51201); // Exceeds 51200 limit

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/vouchers', [
            'amount' => 50,
            'count' => 1,
            'rider_splash' => $longContent,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['rider_splash']);
});

it('validates splash timeout range via API', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/vouchers', [
            'amount' => 50,
            'count' => 1,
            'rider_splash_timeout' => 61, // Exceeds max of 60
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['rider_splash_timeout']);
});

it('generates voucher with complex markdown splash content via API', function () {
    $markdownContent = <<<'MD'
# Welcome to Your Voucher!

## Important Information
- Amount: ₱50.00
- Valid for 30 days
- Redeem at any participating merchant

**Please read the terms and conditions carefully.**

[View Full Terms](https://example.com/terms)
MD;

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/vouchers', [
            'amount' => 50,
            'count' => 1,
            'rider_splash' => $markdownContent,
            'rider_splash_timeout' => 15,
        ]);

    $response->assertStatus(201);

    $voucherCode = $response->json('data.vouchers.0.code');
    $voucher = Voucher::where('code', $voucherCode)->first();

    expect($voucher->instructions->rider->splash)->toBe($markdownContent)
        ->and($voucher->instructions->rider->splash_timeout)->toBe(15);
});
