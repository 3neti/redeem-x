<?php

use App\Models\InstructionItem;
use App\Models\User;
use App\Repositories\InstructionItemRepository;
use App\Services\InstructionCostEvaluator;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// CashInstructionData — slice fields
// ---------------------------------------------------------------------------

test('CashInstructionData accepts null slice_mode by default', function () {
    $data = CashInstructionData::from([
        'amount' => 5000,
        'currency' => 'PHP',
        'validation' => ['secret' => null, 'mobile' => null, 'payable' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
    ]);

    expect($data->slice_mode)->toBeNull();
    expect($data->slices)->toBeNull();
    expect($data->max_slices)->toBeNull();
    expect($data->min_withdrawal)->toBeNull();
});

test('CashInstructionData accepts fixed slice mode', function () {
    $data = CashInstructionData::from([
        'amount' => 5000,
        'currency' => 'PHP',
        'validation' => ['secret' => null, 'mobile' => null, 'payable' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        'slice_mode' => 'fixed',
        'slices' => 5,
    ]);

    expect($data->slice_mode)->toBe('fixed');
    expect($data->slices)->toBe(5);
});

test('CashInstructionData accepts open slice mode', function () {
    $data = CashInstructionData::from([
        'amount' => 5000,
        'currency' => 'PHP',
        'validation' => ['secret' => null, 'mobile' => null, 'payable' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        'slice_mode' => 'open',
        'max_slices' => 10,
        'min_withdrawal' => 100,
    ]);

    expect($data->slice_mode)->toBe('open');
    expect($data->max_slices)->toBe(10);
    expect($data->min_withdrawal)->toBe(100.0);
});

// ---------------------------------------------------------------------------
// Voucher model — computed properties
// ---------------------------------------------------------------------------

test('voucher isDivisible returns false for null slice_mode', function () {
    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    expect($voucher->isDivisible())->toBeFalse();
});

test('voucher isDivisible returns true for fixed slice_mode', function () {
    $user = User::factory()->create();
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'fixed';
    $base['cash']['slices'] = 5;
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    expect($voucher->isDivisible())->toBeTrue();
});

test('voucher getSliceAmount returns correct amount for fixed mode', function () {
    $user = User::factory()->create();
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'fixed';
    $base['cash']['slices'] = 5;
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    expect($voucher->getSliceAmount())->toBe(1000.0);
});

test('voucher getMaxSlices returns slices for fixed and max_slices for open', function () {
    $user = User::factory()->create();

    // Fixed mode
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'fixed';
    $base['cash']['slices'] = 5;
    $fixedVoucher = Vouchers::withMetadata(['instructions' => VoucherInstructionsData::from($base)->toCleanArray()])
        ->withOwner($user)
        ->create();

    expect($fixedVoucher->getMaxSlices())->toBe(5);

    // Open mode
    $base['cash']['slice_mode'] = 'open';
    $base['cash']['slices'] = null;
    $base['cash']['max_slices'] = 10;
    $base['cash']['min_withdrawal'] = 100;
    $openVoucher = Vouchers::withMetadata(['instructions' => VoucherInstructionsData::from($base)->toCleanArray()])
        ->withOwner($user)
        ->create();

    expect($openVoucher->getMaxSlices())->toBe(10);
});

test('voucher getMinWithdrawal returns slice amount for fixed, min_withdrawal for open', function () {
    $user = User::factory()->create();

    // Fixed: min withdrawal = slice amount
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'fixed';
    $base['cash']['slices'] = 5;
    $fixedVoucher = Vouchers::withMetadata(['instructions' => VoucherInstructionsData::from($base)->toCleanArray()])
        ->withOwner($user)
        ->create();

    expect($fixedVoucher->getMinWithdrawal())->toBe(1000.0);

    // Open: min withdrawal from instructions
    $base['cash']['slice_mode'] = 'open';
    $base['cash']['slices'] = null;
    $base['cash']['max_slices'] = 10;
    $base['cash']['min_withdrawal'] = 200;
    $openVoucher = Vouchers::withMetadata(['instructions' => VoucherInstructionsData::from($base)->toCleanArray()])
        ->withOwner($user)
        ->create();

    expect($openVoucher->getMinWithdrawal())->toBe(200.0);
});

test('voucher canWithdraw returns false for unredeemed voucher', function () {
    $user = User::factory()->create();
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'fixed';
    $base['cash']['slices'] = 5;
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    // Not redeemed yet — canWithdraw must be false
    expect($voucher->canWithdraw())->toBeFalse();
});

test('voucher canWithdraw returns false for non-divisible redeemed voucher', function () {
    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    // Redeem it
    $contact = \LBHurtado\Contact\Models\Contact::factory()->create();
    Vouchers::redeem($voucher->code, $contact);

    expect($voucher->fresh()->canWithdraw())->toBeFalse();
});

test('voucher canWithdraw returns false for expired divisible voucher', function () {
    $user = User::factory()->create();
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'fixed';
    $base['cash']['slices'] = 5;
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    // Redeem first, then expire
    $contact = \LBHurtado\Contact\Models\Contact::factory()->create();
    Vouchers::redeem($voucher->code, $contact);
    $voucher->update(['expires_at' => now()->subDay()]);

    expect($voucher->fresh()->canWithdraw())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Pricing — transaction fee × slices
// ---------------------------------------------------------------------------

test('pricing charges base transaction fee plus separate slice fee for fixed mode', function () {
    $user = User::factory()->create();

    // Create the cash.amount InstructionItem (₱15 = 1500 centavos)
    InstructionItem::create([
        'index' => 'cash.amount',
        'name' => 'Transaction Fee',
        'type' => 'cash',
        'price' => 1500,
        'currency' => 'PHP',
        'meta' => ['label' => 'Transaction Fee', 'description' => 'InstaPay fund transfer cost'],
    ]);

    // Ensure the cash.slice_fee InstructionItem exists (₱15 per additional slice)
    InstructionItem::firstOrCreate(
        ['index' => 'cash.slice_fee'],
        [
            'name' => 'Slice Fee',
            'type' => 'amount',
            'price' => 1500,
            'currency' => 'PHP',
            'meta' => ['label' => 'Slice Disbursement Fee', 'category' => 'base', 'description' => 'Per-slice fund transfer cost'],
        ]
    );

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'fixed';
    $base['cash']['slices'] = 5;
    $instructions = VoucherInstructionsData::from($base);

    $evaluator = new InstructionCostEvaluator(new InstructionItemRepository);
    $charges = $evaluator->evaluate($user, $instructions);

    // cash.amount stays at base price (1500) — covers slice 1
    $txnFee = $charges->firstWhere('index', 'cash.amount');
    expect($txnFee)->not->toBeNull();
    expect($txnFee['unit_price'])->toBe(1500);

    // cash.slice_fee covers additional slices (5 - 1 = 4)
    $sliceFee = $charges->firstWhere('index', 'cash.slice_fee');
    expect($sliceFee)->not->toBeNull();
    expect($sliceFee['unit_price'])->toBe(1500);
    expect($sliceFee['slice_count'])->toBe(4);
    expect($sliceFee['pay_count'])->toBe(4);
    expect($sliceFee['price'])->toBe(1500 * 4 * 1); // 4 slices × 1 voucher
});

test('pricing charges base transaction fee plus separate slice fee for open mode', function () {
    $user = User::factory()->create();

    InstructionItem::create([
        'index' => 'cash.amount',
        'name' => 'Transaction Fee',
        'type' => 'cash',
        'price' => 1500,
        'currency' => 'PHP',
        'meta' => ['label' => 'Transaction Fee', 'description' => 'InstaPay fund transfer cost'],
    ]);

    InstructionItem::firstOrCreate(
        ['index' => 'cash.slice_fee'],
        [
            'name' => 'Slice Fee',
            'type' => 'amount',
            'price' => 1500,
            'currency' => 'PHP',
            'meta' => ['label' => 'Slice Disbursement Fee', 'category' => 'base', 'description' => 'Per-slice fund transfer cost'],
        ]
    );

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $base['cash']['slice_mode'] = 'open';
    $base['cash']['max_slices'] = 10;
    $base['cash']['min_withdrawal'] = 100;
    $instructions = VoucherInstructionsData::from($base);

    $evaluator = new InstructionCostEvaluator(new InstructionItemRepository);
    $charges = $evaluator->evaluate($user, $instructions);

    // cash.amount stays at base price (1500)
    $txnFee = $charges->firstWhere('index', 'cash.amount');
    expect($txnFee)->not->toBeNull();
    expect($txnFee['unit_price'])->toBe(1500);

    // cash.slice_fee covers additional slices (10 - 1 = 9)
    $sliceFee = $charges->firstWhere('index', 'cash.slice_fee');
    expect($sliceFee)->not->toBeNull();
    expect($sliceFee['unit_price'])->toBe(1500);
    expect($sliceFee['slice_count'])->toBe(9);
    expect($sliceFee['pay_count'])->toBe(9);
    expect($sliceFee['price'])->toBe(1500 * 9 * 1); // 9 slices × 1 voucher
});

test('pricing unchanged for non-divisible voucher', function () {
    $user = User::factory()->create();

    InstructionItem::create([
        'index' => 'cash.amount',
        'name' => 'Transaction Fee',
        'type' => 'cash',
        'price' => 1500,
        'currency' => 'PHP',
        'meta' => ['label' => 'Transaction Fee', 'description' => 'InstaPay fund transfer cost'],
    ]);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 5000;
    $instructions = VoucherInstructionsData::from($base);

    $evaluator = new InstructionCostEvaluator(new InstructionItemRepository);
    $charges = $evaluator->evaluate($user, $instructions);

    $txnFee = $charges->firstWhere('index', 'cash.amount');

    expect($txnFee)->not->toBeNull();
    expect($txnFee['unit_price'])->toBe(1500);
});
