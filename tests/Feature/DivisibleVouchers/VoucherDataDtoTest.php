<?php

use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function createDtoVoucher(User $user, array $cashOverrides = []): Voucher
{
    $user->deposit(1000000);
    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash'] = array_merge($base['cash'], ['amount' => 5000], $cashOverrides);
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);

    return GenerateVouchers::run($instructions)->first()->fresh();
}

// ---------------------------------------------------------------------------
// VoucherData includes slice fields for divisible voucher
// ---------------------------------------------------------------------------

test('VoucherData includes slice fields for fixed-mode voucher', function () {
    $user = User::factory()->create();
    $voucher = createDtoVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);

    $data = VoucherData::fromModel($voucher);

    expect($data->slice_mode)->toBe('fixed');
    expect($data->max_slices)->toBe(5);
    expect($data->slice_amount)->toBe(1000.0);
    expect($data->min_withdrawal)->toBe(1000.0); // fixed: slice amount
    expect($data->consumed_slices)->toBe(0);
    expect($data->remaining_slices)->toBe(5);
    expect($data->remaining_balance)->toBe(5000.0);
    expect($data->can_withdraw)->toBeFalse(); // Not yet redeemed
});

test('VoucherData includes slice fields for open-mode voucher', function () {
    $user = User::factory()->create();
    $voucher = createDtoVoucher($user, [
        'slice_mode' => 'open',
        'max_slices' => 10,
        'min_withdrawal' => 100,
    ]);

    $data = VoucherData::fromModel($voucher);

    expect($data->slice_mode)->toBe('open');
    expect($data->max_slices)->toBe(10);
    expect($data->slice_amount)->toBeNull(); // open mode: no fixed slice amount
    expect($data->min_withdrawal)->toBe(100.0);
    expect($data->can_withdraw)->toBeFalse(); // Not yet redeemed
});

test('VoucherData has null slice fields for non-divisible voucher', function () {
    $user = User::factory()->create();
    $voucher = createDtoVoucher($user); // No slice_mode

    $data = VoucherData::fromModel($voucher);

    expect($data->slice_mode)->toBeNull();
    expect($data->max_slices)->toBeNull();
    expect($data->slice_amount)->toBeNull();
    expect($data->min_withdrawal)->toBeNull();
    expect($data->consumed_slices)->toBe(0);
    expect($data->remaining_slices)->toBe(0);
    expect($data->can_withdraw)->toBeFalse();
});

test('VoucherData can_withdraw is true for redeemed divisible voucher with remaining slices', function () {
    $user = User::factory()->create();
    $voucher = createDtoVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    Vouchers::redeem($voucher->code, $contact);

    // Simulate first withdrawal
    \LBHurtado\Wallet\Actions\WithdrawCash::run($voucher->fresh()->cash, null, null, [
        'flow' => 'redeem',
        'voucher_code' => $voucher->code,
    ], 100000);

    $data = VoucherData::fromModel($voucher->fresh());

    expect($data->can_withdraw)->toBeTrue();
    expect($data->consumed_slices)->toBe(1);
    expect($data->remaining_slices)->toBe(4);
    expect($data->remaining_balance)->toBe(4000.0);
});

test('VoucherData includes disbursements array for divisible voucher', function () {
    $user = User::factory()->create();
    $voucher = createDtoVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);

    $data = VoucherData::fromModel($voucher);

    expect($data->disbursements)->toBeArray();
    expect($data->disbursements)->toBeEmpty(); // No disbursements yet
});

test('VoucherData display_status unchanged for redeemed divisible voucher', function () {
    $user = User::factory()->create();
    $voucher = createDtoVoucher($user, ['slice_mode' => 'fixed', 'slices' => 5]);
    $contact = Contact::factory()->create(['bank_account' => 'BDO:1234567890']);
    Vouchers::redeem($voucher->code, $contact);

    $data = VoucherData::fromModel($voucher->fresh());

    // Still shows "redeemed" even though slices remain
    expect($data->status)->toBe('redeemed');
});
