<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Actions\Payment\PayWithVoucher;
use App\Models\User;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Cash\Models\Cash;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Bavix\Wallet\Models\Transfer;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed instruction items for proper voucher generation
    $this->seed(\Database\Seeders\InstructionItemSeeder::class);
});

// Helper function to generate vouchers
function generateVoucher(User $issuer, array $overrides = []): Voucher {
    actingAs($issuer);
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $instructions = VoucherInstructionsData::from(array_merge($base, $overrides));
    return GenerateVouchers::run($instructions)->first();
}

test('transfers money from Cash wallet to User wallet', function () {
    // Arrange: Create issuer and generate voucher
    $issuer = User::factory()->create();
    $issuer->depositFloat(1000); // Give issuer funds
    
    $voucher = generateVoucher($issuer);
    
    // Create redeemer
    $redeemer = User::factory()->create();
    
    // Get initial balances
    $cashBalanceBefore = $voucher->cash->balanceFloat;
    $redeemerBalanceBefore = $redeemer->balanceFloat;
    $voucherAmount = $voucher->amount;
    
    dump([
        'cash_balance_before' => $cashBalanceBefore,
        'redeemer_balance_before' => $redeemerBalanceBefore,
        'voucher_amount' => $voucherAmount,
        'issuer_balance' => $issuer->fresh()->balanceFloat,
    ]);
    
    // Act: Pay with voucher
    $result = PayWithVoucher::run($redeemer, $voucher->code);
    
    // Assert: Cash wallet decreased
    expect($voucher->cash->fresh()->balanceFloat)
        ->toBe($cashBalanceBefore - $voucherAmount);
    
    // Assert: User wallet increased
    expect($redeemer->fresh()->balanceFloat)
        ->toBe($redeemerBalanceBefore + $voucherAmount);
    
    // Assert: Success response
    expect($result['success'])->toBeTrue();
    expect($result['amount'])->toBe($voucherAmount);
    expect($result['new_balance'])->toBe($redeemerBalanceBefore + $voucherAmount);
});

test('marks voucher as redeemed with correct metadata', function () {
    // Arrange
    $issuer = User::factory()->create();
    $issuer->depositFloat(1000);
    
    $voucher = generateVoucher($issuer);
    
    $redeemer = User::factory()->create();
    
    // Act
    PayWithVoucher::run($redeemer, $voucher->code);
    
    // Assert: Voucher marked as redeemed
    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();
    
    // Assert: Metadata includes redemption type
    expect($voucher->meta['redemption_type'])->toBe('voucher_payment');
    expect($voucher->meta['redeemer_user_id'])->toBe($redeemer->id);
    expect($voucher->meta['transfer_uuid'])->not->toBeNull();
});

test('creates Transfer transaction record', function () {
    // Arrange
    $issuer = User::factory()->create();
    $issuer->depositFloat(1000);
    
    $voucher = generateVoucher($issuer);
    
    $redeemer = User::factory()->create();
    
    // Act
    PayWithVoucher::run($redeemer, $voucher->code);
    
    // Assert: Transfer exists
    $transfer = Transfer::where('from_type', Cash::class)
        ->where('from_id', $voucher->cash->id)
        ->where('to_type', User::class)
        ->where('to_id', $redeemer->id)
        ->first();
    
    expect($transfer)->not->toBeNull();
    expect($transfer->meta['voucher_code'])->toBe($voucher->code);
    expect($transfer->meta['type'])->toBe('voucher_payment');
});

test('rejects already redeemed voucher', function () {
    // Arrange
    $issuer = User::factory()->create();
    $issuer->depositFloat(1000);
    
    $voucher = generateVoucher($issuer);
    
    $redeemer = User::factory()->create();
    
    // Redeem once
    PayWithVoucher::run($redeemer, $voucher->code);
    
    // Act & Assert: Second redemption should fail
    PayWithVoucher::run($redeemer, $voucher->code);
})->throws(\Illuminate\Validation\ValidationException::class);

test('rejects expired voucher', function () {
    // Arrange: Create voucher that's already expired
    $issuer = User::factory()->create();
    $issuer->depositFloat(1000);
    
    $voucher = generateVoucher($issuer, ['ttl' => 'PT1S']); // 1 second TTL
    
    $redeemer = User::factory()->create();
    
    // Wait for expiry
    sleep(2);
    
    // Act & Assert
    PayWithVoucher::run($redeemer, $voucher->code);
})->throws(\Illuminate\Validation\ValidationException::class);

test('rejects voucher that has not started', function () {
    // Arrange: Create voucher with future start date
    $issuer = User::factory()->create();
    $issuer->depositFloat(1000);
    
    $voucher = generateVoucher($issuer);
    
    // Manually set future start date
    $voucher->update(['starts_at' => now()->addDay()]);
    
    $redeemer = User::factory()->create();
    
    // Act & Assert
    PayWithVoucher::run($redeemer, $voucher->code);
})->throws(\Illuminate\Validation\ValidationException::class);

test('rejects invalid voucher code', function () {
    $redeemer = User::factory()->create();
    
    // Act & Assert
    PayWithVoucher::run($redeemer, 'INVALID-CODE-12345');
})->throws(\Illuminate\Validation\ValidationException::class);

test('issuer can reclaim own voucher', function () {
    // Arrange: Issuer generates voucher
    $issuer = User::factory()->create();
    $initialBalance = 1000;
    $issuer->depositFloat($initialBalance);
    
    $voucher = generateVoucher($issuer);
    
    $balanceAfterGeneration = $issuer->fresh()->balanceFloat;
    $voucherAmount = $voucher->amount;
    
    // Act: Issuer redeems own voucher
    PayWithVoucher::run($issuer, $voucher->code);
    
    // Assert: Issuer gets back the voucher value (not the fees)
    expect($issuer->fresh()->balanceFloat)
        ->toBe($balanceAfterGeneration + $voucherAmount);
});

test('normalizes voucher code to uppercase', function () {
    // Arrange
    $issuer = User::factory()->create();
    $issuer->depositFloat(1000);
    
    $voucher = generateVoucher($issuer);
    
    $redeemer = User::factory()->create();
    
    // Act: Submit lowercase code
    $result = PayWithVoucher::run($redeemer, strtolower($voucher->code));
    
    // Assert: Still works
    expect($result['success'])->toBeTrue();
    expect($result['voucher_code'])->toBe($voucher->code);
});

test('includes issuer_id in transaction metadata', function () {
    // Arrange
    $issuer = User::factory()->create();
    $issuer->depositFloat(1000);
    
    $voucher = generateVoucher($issuer);
    
    $redeemer = User::factory()->create();
    
    // Act
    PayWithVoucher::run($redeemer, $voucher->code);
    
    // Assert: Transfer metadata includes issuer
    $transfer = Transfer::where('from_type', Cash::class)
        ->where('from_id', $voucher->cash->id)
        ->latest()
        ->first();
    
    expect($transfer->meta['issuer_id'])->toBe($issuer->id);
    expect($transfer->meta['voucher_uuid'])->toBe($voucher->uuid);
});

test('bypasses post-redemption pipeline', function () {
    // Arrange: Create voucher with disbursement enabled
    config(['payment-gateway.disburse_disable' => false]);
    
    $issuer = User::factory()->create();
    $issuer->depositFloat(1000);
    
    $voucher = generateVoucher($issuer);
    
    $redeemer = User::factory()->create();
    
    // Act: Pay with voucher
    PayWithVoucher::run($redeemer, $voucher->code);
    
    // Assert: No disbursement metadata (pipeline not triggered)
    $voucher->refresh();
    expect($voucher->metadata['disbursement'] ?? null)->toBeNull();
    
    // Assert: Only payment metadata exists
    expect($voucher->meta['redemption_type'])->toBe('voucher_payment');
});
