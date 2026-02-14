<?php

use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;

function createVoucher(array $attributes = []): Voucher
{
    $user = \App\Models\User::factory()->create();
    
    return Voucher::create(array_merge([
        'code' => strtoupper(\Illuminate\Support\Str::random(4)),
        'owner_type' => get_class($user),
        'owner_id' => $user->id,
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'count' => 1,
            ],
        ],
        'state' => VoucherState::ACTIVE,
        'voucher_type' => 'redeemable',
    ], $attributes));
}

test('voucher can transition from active to locked', function () {
    $voucher = createVoucher(['state' => VoucherState::ACTIVE]);
    
    $voucher->update(['state' => VoucherState::LOCKED]);
    
    expect($voucher->fresh()->state)->toBe(VoucherState::LOCKED);
});


test('voucher can transition from locked to active', function () {
    $voucher = createVoucher(['state' => VoucherState::LOCKED]);
    
    $voucher->update(['state' => VoucherState::ACTIVE]);
    
    expect($voucher->fresh()->state)->toBe(VoucherState::ACTIVE);
});

test('voucher can transition from active to closed', function () {
    $voucher = createVoucher(['state' => VoucherState::ACTIVE]);
    
    $voucher->update(['state' => VoucherState::CLOSED]);
    
    expect($voucher->fresh()->state)->toBe(VoucherState::CLOSED);
});

test('voucher can transition from active to cancelled', function () {
    $voucher = createVoucher(['state' => VoucherState::ACTIVE]);
    
    $voucher->update(['state' => VoucherState::CANCELLED]);
    
    expect($voucher->fresh()->state)->toBe(VoucherState::CANCELLED);
});

test('voucher can cancel with expired date', function () {
    $voucher = createVoucher(['state' => VoucherState::ACTIVE]);
    
    $voucher->update([
        'state' => VoucherState::CANCELLED,
        'expires_at' => now(),
    ]);
    
    $fresh = $voucher->fresh();
    expect($fresh->state)->toBe(VoucherState::CANCELLED);
    expect($fresh->expires_at)->not->toBeNull();
});

test('locked voucher prevents redemption', function () {
    $voucher = createVoucher(['state' => VoucherState::LOCKED]);
    
    expect($voucher->canRedeem())->toBeFalse();
});

test('cancelled voucher prevents redemption', function () {
    $voucher = createVoucher(['state' => VoucherState::CANCELLED]);
    
    expect($voucher->canRedeem())->toBeFalse();
});

test('closed voucher prevents redemption', function () {
    $voucher = createVoucher(['state' => VoucherState::CLOSED]);
    
    expect($voucher->canRedeem())->toBeFalse();
});

test('active voucher allows redemption', function () {
    $voucher = createVoucher([
        'state' => VoucherState::ACTIVE,
        'expires_at' => now()->addDays(7),
    ]);
    
    expect($voucher->canRedeem())->toBeTrue();
});

test('it detects locked state', function () {
    $voucher = createVoucher(['state' => VoucherState::LOCKED]);
    
    expect($voucher->isLocked())->toBeTrue();
});

test('it detects closed state', function () {
    $voucher = createVoucher(['state' => VoucherState::CLOSED]);
    
    expect($voucher->isClosed())->toBeTrue();
});

test('it can bulk update state', function () {
    $vouchers = collect([
        createVoucher(['state' => VoucherState::ACTIVE]),
        createVoucher(['state' => VoucherState::ACTIVE]),
        createVoucher(['state' => VoucherState::ACTIVE]),
    ]);

    Voucher::whereIn('id', $vouchers->pluck('id'))
        ->update(['state' => VoucherState::LOCKED]);

    $vouchers->each(function ($voucher) {
        expect($voucher->fresh()->state)->toBe(VoucherState::LOCKED);
    });
});
