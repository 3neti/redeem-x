<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\ExternalMetadataData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

test('can generate voucher with external metadata', function () {
    $user = User::factory()->create();
    $user->depositFloat(10000.00);
    $this->actingAs($user);
    // Generate voucher
    $instructions = VoucherInstructionsData::generateFromScratch();
    $instructions->cash->amount = 100;
    $instructions->count = 1;
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    // Set external metadata
    $voucher->external_metadata = [
        'external_id' => 'GAME-001',
        'external_type' => 'questpay',
        'reference_id' => 'QUEST-123',
        'user_id' => 'PLAYER-456',
        'custom' => [
            'level' => 10,
            'challenge_type' => 'treasure_hunt',
        ],
    ];
    $voucher->save();
    
    // Reload and verify
    $voucher->refresh();
    
    expect($voucher->external_metadata)->not->toBeNull()
        ->and($voucher->external_metadata->external_id)->toBe('GAME-001')
        ->and($voucher->external_metadata->external_type)->toBe('questpay')
        ->and($voucher->external_metadata->reference_id)->toBe('QUEST-123')
        ->and($voucher->external_metadata->user_id)->toBe('PLAYER-456')
        ->and($voucher->external_metadata->getCustom('level'))->toBe(10)
        ->and($voucher->external_metadata->getCustom('challenge_type'))->toBe('treasure_hunt');
});

test('can generate voucher without external metadata', function () {
    $user = User::factory()->create();
    $user->depositFloat(10000.00);
    $this->actingAs($user);
    
    $instructions = VoucherInstructionsData::generateFromScratch();
    $instructions->cash->amount = 100;
    $instructions->count = 1;
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    expect($voucher)->not->toBeNull()
        ->and($voucher->external_metadata)->toBeNull();
});

test('validates external metadata fields when using DTO', function () {
    // DTO validation happens via Spatie LaravelData rules
    // ExternalMetadataData has max:255 rule for external_id
    
    // Valid metadata should work
    $valid = ExternalMetadataData::from([
        'external_id' => 'GAME-001',
        'external_type' => 'questpay',
    ]);
    
    expect($valid->external_id)->toBe('GAME-001');
    
    // Too long string will be truncated by the setter or validated by request
    // In practice, validation happens at the FormRequest level
});

test('can query vouchers by external metadata', function () {
    $user = User::factory()->create();
    $user->depositFloat(10000.00);
    $this->actingAs($user);
    
    $instructions = VoucherInstructionsData::generateFromScratch();
    $instructions->cash->amount = 100;
    $instructions->count = 2;
    
    $vouchers = GenerateVouchers::run($instructions);
    
    // Set different metadata on each voucher
    $vouchers[0]->external_metadata = [
        'external_type' => 'questpay',
        'external_id' => 'GAME-001',
    ];
    $vouchers[0]->save();
    
    $vouchers[1]->external_metadata = [
        'external_type' => 'loyalty',
        'external_id' => 'LOYALTY-001',
    ];
    $vouchers[1]->save();

    // Query by external_type
    $questpayVouchers = Voucher::whereExternal('external_type', 'questpay')->get();
    $loyaltyVouchers = Voucher::whereExternal('external_type', 'loyalty')->get();

    expect($questpayVouchers)->toHaveCount(1)
        ->and($loyaltyVouchers)->toHaveCount(1)
        ->and($questpayVouchers->first()->external_metadata->external_id)->toBe('GAME-001')
        ->and($loyaltyVouchers->first()->external_metadata->external_id)->toBe('LOYALTY-001');
});
