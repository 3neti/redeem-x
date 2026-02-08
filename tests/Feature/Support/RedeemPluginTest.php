<?php

use App\Support\RedeemPluginMap;
use App\Support\RedeemPluginSelector;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

test('RedeemPluginMap can get fields for a plugin', function () {
    $fields = RedeemPluginMap::fieldsFor('inputs');

    expect($fields)->toBeArray();
    expect($fields)->toContain(VoucherInputField::EMAIL);
    expect($fields)->toContain(VoucherInputField::NAME);
});

test('RedeemPluginMap returns empty array for disabled plugin', function () {
    $fields = RedeemPluginMap::fieldsFor('selfie'); // Disabled by default

    expect($fields)->toBeEmpty();
});

test('RedeemPluginMap can get all enabled plugins', function () {
    $plugins = RedeemPluginMap::allPlugins();

    expect($plugins)->toBeArray();
    expect($plugins)->toContain('inputs');
    expect($plugins)->toContain('signature');
});

test('RedeemPluginMap can get first plugin', function () {
    $first = RedeemPluginMap::firstPlugin();

    expect($first)->toBeString();
});

test('RedeemPluginMap can get next plugin after current', function () {
    $first = RedeemPluginMap::firstPlugin();
    $next = RedeemPluginMap::nextPluginAfter($first);

    if ($next) {
        expect($next)->toBeString();
        expect($next)->not->toBe($first);
    } else {
        expect($next)->toBeNull();
    }
});

test('RedeemPluginMap can check if plugin is enabled', function () {
    expect(RedeemPluginMap::isEnabled('inputs'))->toBeTrue();
    expect(RedeemPluginMap::isEnabled('selfie'))->toBeFalse();
});

test('RedeemPluginMap can get page for plugin', function () {
    $page = RedeemPluginMap::pageFor('inputs');

    expect($page)->toBe('Redeem/Inputs');
});

test('RedeemPluginMap can get session key for plugin', function () {
    $sessionKey = RedeemPluginMap::sessionKeyFor('inputs');

    expect($sessionKey)->toBe('inputs');
});

test('RedeemPluginSelector selects plugins based on voucher fields', function () {
    $user = \App\Models\User::factory()->create();

    // Create voucher requiring NAME and EMAIL
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['name', 'email']];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    $plugins = RedeemPluginSelector::fromVoucher($voucher);

    expect($plugins)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($plugins->toArray())->toContain('inputs'); // NAME and EMAIL are in 'inputs' plugin
    expect($plugins->toArray())->not->toContain('signature'); // SIGNATURE not required
});

test('RedeemPluginSelector selects signature plugin when signature required', function () {
    $user = \App\Models\User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['signature']];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    $plugins = RedeemPluginSelector::fromVoucher($voucher);

    expect($plugins->toArray())->toContain('signature');
    expect($plugins->toArray())->not->toContain('inputs');
});

test('RedeemPluginSelector selects multiple plugins when needed', function () {
    $user = \App\Models\User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['name', 'email', 'signature']];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    $plugins = RedeemPluginSelector::fromVoucher($voucher);

    expect($plugins->count())->toBeGreaterThanOrEqual(2);
    expect($plugins->toArray())->toContain('inputs');
    expect($plugins->toArray())->toContain('signature');
});

test('RedeemPluginSelector can get requested fields for plugin', function () {
    $user = \App\Models\User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['name', 'email', 'address']];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    $requestedFields = RedeemPluginSelector::requestedFieldsFor('inputs', $voucher);

    expect($requestedFields)->toContain('name');
    expect($requestedFields)->toContain('email');
    expect($requestedFields)->toContain('address');
    expect($requestedFields)->not->toContain('signature'); // Not in inputs plugin
});

test('RedeemPluginSelector can check if voucher requires plugin', function () {
    $user = \App\Models\User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['name']];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    expect(RedeemPluginSelector::voucherRequiresPlugin($voucher, 'inputs'))->toBeTrue();
    expect(RedeemPluginSelector::voucherRequiresPlugin($voucher, 'signature'))->toBeFalse();
});

test('RedeemPluginSelector can get first plugin for voucher', function () {
    $user = \App\Models\User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['name', 'signature']];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    $first = RedeemPluginSelector::firstPluginFor($voucher);

    expect($first)->toBeString();
});

test('RedeemPluginSelector can get next plugin for voucher', function () {
    $user = \App\Models\User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['name', 'signature']];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    $first = RedeemPluginSelector::firstPluginFor($voucher);
    $next = RedeemPluginSelector::nextPluginFor($voucher, $first);

    // Should have at least 2 plugins (inputs + signature)
    expect($next)->toBeString();
    expect($next)->not->toBe($first);
});
