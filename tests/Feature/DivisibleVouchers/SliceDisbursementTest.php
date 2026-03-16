<?php

use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\DisbursementData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\WithdrawCash;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper: create a voucher with a funded cash wallet via GenerateVouchers
// ---------------------------------------------------------------------------

function createFundedVoucher(User $user, array $cashOverrides = []): Voucher
{
    $user->deposit(1000000); // Fund user wallet (enough for any test)
    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash'] = array_merge($base['cash'], ['amount' => 5000], $cashOverrides);
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    return $vouchers->first()->fresh();
}

// ---------------------------------------------------------------------------
// WithdrawCash — partial amount support
// ---------------------------------------------------------------------------

test('WithdrawCash with explicit amount withdraws exactly that amount', function () {
    $user = User::factory()->create();
    $voucher = createFundedVoucher($user);
    $cash = $voucher->cash;

    // Withdraw 1000 pesos = 100000 centavos
    $transaction = WithdrawCash::run($cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 100000);

    expect($transaction)->toBeInstanceOf(\Bavix\Wallet\Models\Transaction::class);
    expect(abs($transaction->amount))->toBe(100000);
    // Remaining balance should be 400000 centavos (4000 pesos)
    expect((int) $cash->wallet->fresh()->balance)->toBe(400000);
});

test('WithdrawCash with null amount drains full balance', function () {
    $user = User::factory()->create();
    $voucher = createFundedVoucher($user);
    $cash = $voucher->cash;

    $transaction = WithdrawCash::run($cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ]);

    expect(abs($transaction->amount))->toBe(500000); // 5000 * 100
    expect((int) $cash->wallet->fresh()->balance)->toBe(0);
});

test('WithdrawCash rejects amount exceeding balance', function () {
    $user = User::factory()->create();
    $voucher = createFundedVoucher($user);
    $cash = $voucher->cash;

    WithdrawCash::run($cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 600000); // 6000 pesos > 5000 balance
})->throws(\InvalidArgumentException::class);

// ---------------------------------------------------------------------------
// DisburseInputData — amount override + slice references
// ---------------------------------------------------------------------------

// Helper: redeem and reload voucher with redeemer set (FrittenKeeZ clears the
// public $redeemer property after redeem(), so fresh instances need it re-set)
function redeemAndReload(Voucher $voucher, $contact): Voucher
{
    Vouchers::redeem($voucher->code, $contact);
    $v = Voucher::find($voucher->id);
    $v->load('redeemers');
    $v->redeemer = $v->redeemers->first();

    return $v;
}

test('DisburseInputData fromVoucher with explicit amount uses that amount', function () {
    $user = User::factory()->create();
    $voucher = createFundedVoucher($user);
    $contact = \LBHurtado\Contact\Models\Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemAndReload($voucher, $contact);

    $input = \LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData::fromVoucher($voucher, null, 1000.0);

    expect($input->amount)->toBe(1000.0);
});

test('DisburseInputData fromVoucher with null amount uses full cash amount', function () {
    $user = User::factory()->create();
    $voucher = createFundedVoucher($user);
    $contact = \LBHurtado\Contact\Models\Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemAndReload($voucher, $contact);

    $input = \LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData::fromVoucher($voucher);

    expect($input->amount)->toBe(5000.0);
});

test('DisburseInputData generates per-slice reference for divisible voucher', function () {
    $user = User::factory()->create();
    $voucher = createFundedVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $contact = \LBHurtado\Contact\Models\Contact::factory()->create([
        'mobile' => '+639171234567',
        'bank_account' => 'BDO:1234567890',
    ]);
    $voucher = redeemAndReload($voucher, $contact);

    $input = \LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData::fromVoucher($voucher, null, 1000.0, 2);

    expect($input->reference)->toContain('-S2');
});

test('DisburseInputData keeps current reference format for non-divisible voucher', function () {
    $user = User::factory()->create();
    $voucher = createFundedVoucher($user);
    $contact = \LBHurtado\Contact\Models\Contact::factory()->create([
        'mobile' => '+639171234567',
        'bank_account' => 'BDO:1234567890',
    ]);
    $voucher = redeemAndReload($voucher, $contact);

    $input = \LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData::fromVoucher($voucher);

    expect($input->reference)->not->toContain('-S');
});

// ---------------------------------------------------------------------------
// DisbursementData — plural metadata reads
// ---------------------------------------------------------------------------

test('DisbursementData fromMetadata reads plural disbursements array', function () {
    $metadata = [
        'disbursements' => [
            [
                'gateway' => 'netbank',
                'transaction_id' => 'TXN-001',
                'status' => 'completed',
                'amount' => 1000.0,
                'currency' => 'PHP',
                'recipient_identifier' => '1234567890',
                'disbursed_at' => now()->toIso8601String(),
            ],
            [
                'gateway' => 'netbank',
                'transaction_id' => 'TXN-002',
                'status' => 'completed',
                'amount' => 1000.0,
                'currency' => 'PHP',
                'recipient_identifier' => '1234567890',
                'disbursed_at' => now()->toIso8601String(),
            ],
        ],
        'disbursement' => [
            'gateway' => 'netbank',
            'transaction_id' => 'TXN-002',
            'status' => 'completed',
            'amount' => 1000.0,
            'currency' => 'PHP',
            'recipient_identifier' => '1234567890',
            'disbursed_at' => now()->toIso8601String(),
        ],
    ];

    // fromMetadata should still work (returns latest)
    $latest = DisbursementData::fromMetadata($metadata);
    expect($latest)->not->toBeNull();
    expect($latest->transaction_id)->toBe('TXN-002');

    // New: allFromMetadata should return all disbursements
    $all = DisbursementData::allFromMetadata($metadata);
    expect($all)->toHaveCount(2);
    expect($all[0]->transaction_id)->toBe('TXN-001');
    expect($all[1]->transaction_id)->toBe('TXN-002');
});

test('DisbursementData fromMetadata falls back to singular disbursement', function () {
    $metadata = [
        'disbursement' => [
            'gateway' => 'netbank',
            'transaction_id' => 'TXN-LEGACY',
            'status' => 'completed',
            'amount' => 5000.0,
            'currency' => 'PHP',
            'recipient_identifier' => '1234567890',
            'disbursed_at' => now()->toIso8601String(),
        ],
    ];

    $latest = DisbursementData::fromMetadata($metadata);
    expect($latest)->not->toBeNull();
    expect($latest->transaction_id)->toBe('TXN-LEGACY');

    $all = DisbursementData::allFromMetadata($metadata);
    expect($all)->toHaveCount(1);
    expect($all[0]->transaction_id)->toBe('TXN-LEGACY');
});
