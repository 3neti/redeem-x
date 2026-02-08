<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

// Helper function to create test instructions
function testInstructions(): VoucherInstructionsData
{
    return VoucherInstructionsData::generateFromScratch();
}

test('voucher package is loaded and autoloaded', function () {
    expect(class_exists(Voucher::class))->toBeTrue();
});

test('voucher model can be instantiated', function () {
    $voucher = new Voucher;

    expect($voucher)->toBeInstanceOf(Voucher::class);
});

test('user has vouchers relationship', function () {
    $user = User::factory()->create();

    expect(method_exists($user, 'vouchers'))->toBeTrue()
        ->and(method_exists($user, 'createVoucher'))->toBeTrue();
});

test('voucher table exists in database', function () {
    expect(\Schema::hasTable('vouchers'))->toBeTrue()
        ->and(\Schema::hasTable('voucher_entity'))->toBeTrue();
});

test('user can create voucher', function () {
    $user = User::factory()->create();

    $instructions = testInstructions();
    $voucher = $user->createVoucher(function ($vouchers) use ($instructions) {
        $vouchers->withMetadata(['instructions' => $instructions->toArray()]);
    });

    expect($voucher)->toBeInstanceOf(Voucher::class)
        ->and($voucher->exists)->toBeTrue();
});

test('voucher has required columns', function () {
    $columns = \Schema::getColumnListing('vouchers');

    expect(in_array('id', $columns))->toBeTrue()
        ->and(in_array('code', $columns))->toBeTrue()
        ->and(in_array('created_at', $columns))->toBeTrue();
});

test('voucher belongs to user', function () {
    $user = User::factory()->create();

    $instructions = testInstructions();
    $voucher = $user->createVoucher(function ($vouchers) use ($instructions) {
        $vouchers->withMetadata(['instructions' => $instructions->toArray()]);
    });

    expect($voucher->owner)->toBeInstanceOf(User::class)
        ->and($voucher->owner->id)->toBe($user->id);
});

test('can query user vouchers', function () {
    $user = User::factory()->create();

    $instructions = testInstructions();

    // Create 3 vouchers
    $user->createVoucher(function ($vouchers) use ($instructions) {
        $vouchers->withMetadata(['instructions' => $instructions->toArray()]);
    });
    $user->createVoucher(function ($vouchers) use ($instructions) {
        $vouchers->withMetadata(['instructions' => $instructions->toArray()]);
    });
    $user->createVoucher(function ($vouchers) use ($instructions) {
        $vouchers->withMetadata(['instructions' => $instructions->toArray()]);
    });

    expect($user->vouchers()->count())->toBe(3);
});
