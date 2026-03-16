<?php

use App\Actions\Voucher\WithdrawFromVoucher;
use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper: create a funded voucher
// ---------------------------------------------------------------------------

function createSliceVoucher(User $user, array $cashOverrides = []): Voucher
{
    $user->deposit(1000000);
    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash'] = array_merge($base['cash'], ['amount' => 5000], $cashOverrides);
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);

    return GenerateVouchers::run($instructions)->first()->fresh();
}

function redeemVoucher(Voucher $voucher, Contact $contact): Voucher
{
    Vouchers::redeem($voucher->code, $contact);
    $v = Voucher::find($voucher->id);
    $v->load('redeemers');
    $v->redeemer = $v->redeemers->first();

    return $v;
}

// ---------------------------------------------------------------------------
// Fixed mode: WithdrawFromVoucher
// ---------------------------------------------------------------------------

test('withdraw second slice from fixed-mode voucher succeeds', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $contact);

    // First slice consumed during redemption pipeline (DisburseCash)
    // Simulate first withdrawal manually for unit test isolation
    \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 100000); // 1000 pesos = first slice

    $voucher->refresh();

    // Withdraw second slice
    $result = WithdrawFromVoucher::run($voucher, $contact);

    expect($result)->toBeArray();
    expect($result['success'])->toBeTrue();
    expect($result['amount'])->toBe(1000.0); // ₱1000 per slice
    expect($result['slice_number'])->toBe(2);
    expect($result['remaining_slices'])->toBe(3);
});

test('withdraw all slices from fixed-mode voucher exhausts balance', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $contact);

    // Consume all 5 slices
    for ($i = 0; $i < 5; $i++) {
        \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->cash, null, null, [
            'flow' => 'redeem',
            'voucher_code' => $voucher->code,
        ], 100000);
    }

    $voucher->refresh();

    expect($voucher->canWithdraw())->toBeFalse();
    expect($voucher->getRemainingBalance())->toBe(0.0);
});

test('withdraw rejects different contact than original redeemer', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $originalContact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $originalContact);

    // First slice
    \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 100000);
    $voucher->refresh();

    // Different contact tries to withdraw
    $differentContact = Contact::factory()->create(['mobile' => '+639999999999']);

    WithdrawFromVoucher::run($voucher, $differentContact);
})->throws(\RuntimeException::class, 'Only the original redeemer can withdraw');

test('withdraw from expired voucher is rejected', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $contact);

    // Expire the voucher
    $voucher->update(['expires_at' => now()->subDay()]);
    $voucher->refresh();

    WithdrawFromVoucher::run($voucher, $contact);
})->throws(\RuntimeException::class);

// ---------------------------------------------------------------------------
// Open mode: WithdrawFromVoucher
// ---------------------------------------------------------------------------

test('withdraw from open-mode voucher with valid amount succeeds', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user, [
        'slice_mode' => 'open',
        'max_slices' => 10,
        'min_withdrawal' => 100,
    ]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $contact);

    // First withdrawal
    \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 50000); // 500 pesos
    $voucher->refresh();

    // Second withdrawal with chosen amount
    $result = WithdrawFromVoucher::run($voucher, $contact, 200.0);

    expect($result['success'])->toBeTrue();
    expect($result['amount'])->toBe(200.0);
    expect($result['slice_number'])->toBe(2);
});

test('withdraw rejects amount below min_withdrawal', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user, [
        'slice_mode' => 'open',
        'max_slices' => 10,
        'min_withdrawal' => 100,
    ]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $contact);

    // First withdrawal
    \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 50000);
    $voucher->refresh();

    WithdrawFromVoucher::run($voucher, $contact, 50.0); // Below ₱100 minimum
})->throws(\InvalidArgumentException::class, 'below minimum');

test('withdraw rejects amount exceeding remaining balance', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user, [
        'slice_mode' => 'open',
        'max_slices' => 10,
        'min_withdrawal' => 100,
    ]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $contact);

    // First withdrawal - take 4900, leaving only 100
    \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 490000);
    $voucher->refresh();

    WithdrawFromVoucher::run($voucher, $contact, 200.0); // Only 100 remaining
})->throws(\InvalidArgumentException::class, 'exceeds remaining');

test('withdraw rejects when max_slices exhausted even with balance remaining', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user, [
        'slice_mode' => 'open',
        'max_slices' => 2,
        'min_withdrawal' => 100,
    ]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $contact);

    // Consume both slices with small amounts
    \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 10000); // 100 pesos
    \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 10000); // 100 pesos

    $voucher->refresh();

    // Balance remaining but slices exhausted
    expect($voucher->getRemainingBalance())->toBeGreaterThan(0);
    expect($voucher->canWithdraw())->toBeFalse();

    WithdrawFromVoucher::run($voucher, $contact, 100.0);
})->throws(\RuntimeException::class);

// ---------------------------------------------------------------------------
// General: WithdrawFromVoucher
// ---------------------------------------------------------------------------

test('withdraw from non-divisible voucher is rejected', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user); // No slice_mode
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $contact);

    WithdrawFromVoucher::run($voucher, $contact);
})->throws(\RuntimeException::class, 'not divisible');

test('withdraw reuses bank account from original redeemer', function () {
    $user = User::factory()->create();
    $voucher = createSliceVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    $voucher = redeemVoucher($voucher, $contact);

    // First slice
    \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 100000);
    $voucher->refresh();

    $result = WithdrawFromVoucher::run($voucher, $contact);

    // The result should contain the bank info from the original redeemer's contact
    expect($result['bank_code'])->toBe('BDO');
    expect($result['account_number'])->toBe('1234567890');
});
