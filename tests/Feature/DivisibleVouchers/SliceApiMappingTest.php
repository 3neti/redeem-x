<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    $this->user = User::factory()->create();
    $this->user->depositFloat(100000);
    $this->withHeader('Idempotency-Key', (string) Str::uuid());
});

test('API maps fixed slice fields into voucher instructions', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 5000,
        'count' => 1,
        'slice_mode' => 'fixed',
        'slices' => 5,
    ]);

    $response->assertStatus(201);

    $voucher = Voucher::latest('id')->first();
    $cash = $voucher->instructions->cash;

    expect($cash->slice_mode)->toBe('fixed');
    expect($cash->slices)->toBe(5);
    expect($voucher->isDivisible())->toBeTrue();
    expect($voucher->getSliceAmount())->toBe(1000.0);
});

test('API maps open slice fields into voucher instructions', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 5000,
        'count' => 1,
        'slice_mode' => 'open',
        'max_slices' => 10,
        'min_withdrawal' => 200,
    ]);

    $response->assertStatus(201);

    $voucher = Voucher::latest('id')->first();
    $cash = $voucher->instructions->cash;

    expect($cash->slice_mode)->toBe('open');
    expect($cash->max_slices)->toBe(10);
    expect($cash->min_withdrawal)->toBe(200.0);
    expect($voucher->isDivisible())->toBeTrue();
});

test('API generates non-divisible voucher when no slice fields provided', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 1000,
        'count' => 1,
    ]);

    $response->assertStatus(201);

    $voucher = Voucher::latest('id')->first();
    $cash = $voucher->instructions->cash;

    expect($cash->slice_mode)->toBeNull();
    expect($voucher->isDivisible())->toBeFalse();
});
