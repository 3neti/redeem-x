<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

use function Pest\Laravel\actingAs;

test('GenerateVouchers action returns LBHurtado Voucher instances', function () {
    $user = User::factory()->create();
    $user->deposit(10000); // Give user funds to pay for vouchers

    actingAs($user);

    $instructions = VoucherInstructionsData::generateFromScratch();
    $vouchers = GenerateVouchers::run($instructions);

    expect($vouchers)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($vouchers->first())->toBeInstanceOf(Voucher::class);
    expect($vouchers->first())->toBeInstanceOf(LBHurtado\Voucher\Models\Voucher::class);
});

test('generate vouchers with minimal configuration', function () {
    $user = User::factory()->create();
    $user->deposit(10000);

    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 10;
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    expect($vouchers)->toHaveCount(10);

    foreach ($vouchers as $voucher) {
        expect($voucher)->toBeInstanceOf(Voucher::class);
        expect($voucher->instructions->cash)->toBeObject();
        expect($voucher->expires_at)->not->toBeNull();
    }
});

test('generate vouchers with all configuration options', function () {
    $user = User::factory()->create();
    $user->deposit(20000); // Higher amount for complete config

    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['email', 'name', 'address', 'signature']];
    $base['feedback'] = [
        'email' => 'admin@example.com',
        'mobile' => '+639171234567',
        'webhook' => 'https://example.com/webhook',
    ];
    $base['rider'] = [
        'message' => 'Thank you for redeeming!',
        'url' => 'https://example.com/thanks',
    ];
    $base['count'] = 5;
    $base['prefix'] = 'PROMO';
    $base['mask'] = '****-****-****';
    $base['ttl'] = 'P30D'; // 30 days in ISO 8601 duration format
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    expect($vouchers)->toHaveCount(5);

    $voucher = $vouchers->first();
    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->code)->toStartWith('PROMO');
    expect($voucher->instructions->cash)->toBeObject();
    $fieldValues = array_map(fn ($f) => $f->value, $voucher->instructions->inputs->fields);
    expect($fieldValues)->toContain('email', 'name', 'address', 'signature');
    expect($voucher->instructions->feedback->email)->toBe('admin@example.com');
    expect($voucher->instructions->rider->message)->toBe('Thank you for redeeming!');
    expect(round(abs($voucher->expires_at->diffInDays(now()))))->toBe(30.0);
});

test('generate vouchers with custom prefix and mask', function () {
    $user = User::factory()->create();
    $user->deposit(5000);

    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 3;
    $base['prefix'] = 'TEST';
    $base['mask'] = '****-****';
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    foreach ($vouchers as $voucher) {
        expect($voucher)->toBeInstanceOf(Voucher::class);
        expect($voucher->code)->toStartWith('TEST');
        // The mask '****-****' creates codes like TEST-XXXX-XXXX (note the prefix separator)
        expect($voucher->code)->toMatch('/^TEST-[A-Z0-9]{4}-[A-Z0-9]{4}$/');
    }
});

test('generate vouchers ensures unique codes', function () {
    $user = User::factory()->create();
    $user->deposit(100000); // Large amount for 100 vouchers

    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 100;
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    expect($vouchers)->toHaveCount(100);

    $codes = $vouchers->pluck('code')->toArray();
    expect(array_unique($codes))->toHaveCount(100);
});

test('generate vouchers with zero TTL creates non-expiring vouchers', function () {
    $user = User::factory()->create();
    $user->deposit(5000);

    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 1;
    $base['ttl'] = 'P0D'; // 0 days in ISO 8601 duration format
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    $voucher = $vouchers->first();
    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->expires_at)->toBeNull();
});

test('generate vouchers requires authenticated user', function () {
    // No user authenticated

    $instructions = VoucherInstructionsData::generateFromScratch();

    GenerateVouchers::run($instructions);
})->throws(\Exception::class, 'No authenticated user found');

test('generate vouchers stores instructions in metadata', function () {
    $user = User::factory()->create();
    $user->deposit(10000);

    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['email', 'name']];
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    $voucher = $vouchers->first();
    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->metadata)->toHaveKey('instructions');
    expect($voucher->instructions)->toBeInstanceOf(VoucherInstructionsData::class);
    expect($voucher->instructions->cash)->toBeObject();
    $fieldValues = array_map(fn ($f) => $f->value, $voucher->instructions->inputs->fields);
    expect($fieldValues)->toContain('email', 'name');
});

test('generate vouchers that start in the future', function () {
    $user = User::factory()->create();
    $user->deposit(10000);

    actingAs($user);

    $instructions = VoucherInstructionsData::generateFromScratch();

    $vouchers = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->withStartTime(now()->addDays(7)) // Starts 7 days from now
        ->withExpireTimeIn(\Carbon\CarbonInterval::days(30)) // Expires 30 days after start
        ->create();

    expect($vouchers)->toBeInstanceOf(Voucher::class);
    expect($vouchers->starts_at)->not->toBeNull();
    expect($vouchers->starts_at->isFuture())->toBeTrue();
    expect(round(abs($vouchers->starts_at->diffInDays(now()))))->toBe(7.0);
    expect($vouchers->expires_at)->not->toBeNull();
    expect($vouchers->expires_at->isAfter($vouchers->starts_at))->toBeTrue();
});

test('generate vouchers with extended expiry date', function () {
    $user = User::factory()->create();
    $user->deposit(10000);

    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 1;
    $base['ttl'] = 'P90D'; // 90 days in ISO 8601 duration format
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    $voucher = $vouchers->first();
    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->expires_at)->not->toBeNull();
    expect(round(abs($voucher->expires_at->diffInDays(now()))))->toBe(90.0);
});

test('generate vouchers with success message configuration', function () {
    $user = User::factory()->create();
    $user->deposit(10000);

    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['rider'] = [
        'message' => 'Congratulations! Your redemption is being processed.',
    ];
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    $voucher = $vouchers->first();
    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->instructions->rider->message)->toBe('Congratulations! Your redemption is being processed.');
});

test('generate vouchers with custom rider redirect URL', function () {
    $user = User::factory()->create();
    $user->deposit(10000);

    actingAs($user);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['rider'] = [
        'message' => 'Thank you!',
        'url' => 'https://example.com/custom-thank-you',
    ];
    $instructions = VoucherInstructionsData::from($base);

    $vouchers = GenerateVouchers::run($instructions);

    $voucher = $vouchers->first();
    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->instructions->rider->url)->toBe('https://example.com/custom-thank-you');
});

test('generate vouchers with default rider configuration from config', function () {
    $user = User::factory()->create();
    $user->deposit(10000);

    actingAs($user);

    // Use default configuration (no rider specified)
    $instructions = VoucherInstructionsData::generateFromScratch();

    $vouchers = GenerateVouchers::run($instructions);

    $voucher = $vouchers->first();
    expect($voucher)->toBeInstanceOf(Voucher::class);

    // Check if rider exists in instructions (may be null or have defaults from config)
    $rider = $voucher->instructions->rider ?? null;
    expect($rider)->toBeObject();
});
