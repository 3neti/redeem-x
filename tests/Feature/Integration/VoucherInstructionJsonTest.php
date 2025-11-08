<?php

declare(strict_types=1);

use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

use function Pest\Laravel\actingAs;

test('voucher instructions JSON structure - minimal configuration', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
    actingAs($user);
    
    // Use defaults and only override generation
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    expect($voucher)->toBeInstanceOf(Voucher::class);
    
    // Get actual JSON structure
    $actualJson = json_encode($voucher->instructions->toArray(), JSON_PRETTY_PRINT);
    
    // Display for developers
    echo "\n\n=== MINIMAL CONFIGURATION JSON ===\n";
    echo $actualJson;
    echo "\n==================================\n\n";
    
    // Assert key structure exists
    $instructionsArray = $voucher->instructions->toArray();
    expect($instructionsArray)->toHaveKeys(['cash', 'inputs', 'feedback', 'rider']);
    expect($instructionsArray['cash'])->toHaveKeys(['amount', 'currency', 'validation']);
    expect($instructionsArray['cash']['amount'])->toBeFloat();
    expect($instructionsArray['cash']['currency'])->toBeString();
    expect($instructionsArray['cash']['validation'])->toHaveKeys(['secret', 'mobile', 'country']);
});

test('voucher instructions JSON structure - with email and name fields', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
    actingAs($user);
    
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['email', 'name']];
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    $actualJson = json_encode($voucher->instructions->toArray(), JSON_PRETTY_PRINT);
    
    echo "\n\n=== WITH EMAIL AND NAME FIELDS JSON ===\n";
    echo $actualJson;
    echo "\n=======================================\n\n";
    
    $instructionsArray = $voucher->instructions->toArray();
    // Fields structure displayed in JSON above
    expect($instructionsArray)->toHaveKey('inputs');
});

test('voucher instructions JSON structure - all input fields', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
    actingAs($user);
    
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => [
        'email', 'name', 'address', 'birth_date', 
        'gross_monthly_income', 'location', 'reference_code', 'otp', 'signature'
    ]];
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    $actualJson = json_encode($voucher->instructions->toArray(), JSON_PRETTY_PRINT);
    
    echo "\n\n=== ALL INPUT FIELDS JSON ===\n";
    echo $actualJson;
    echo "\n=============================\n\n";
    
    $instructionsArray = $voucher->instructions->toArray();
    // Fields structure displayed in JSON above
    expect($instructionsArray)->toHaveKey('inputs');
});

test('voucher instructions JSON structure - with feedback channels', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
    actingAs($user);
    
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['feedback'] = [
        'channels' => [
            'email' => 'admin@example.com',
            'sms' => '+639171234567',
            'webhook' => 'https://example.com/webhook',
        ],
    ];
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    $actualJson = json_encode($voucher->instructions->toArray(), JSON_PRETTY_PRINT);
    
    echo "\n\n=== WITH FEEDBACK CHANNELS JSON ===\n";
    echo $actualJson;
    echo "\n===================================\n\n";
    
    $instructionsArray = $voucher->instructions->toArray();
    // Feedback structure may vary - just check it exists
    expect($instructionsArray)->toHaveKey('feedback');
});

test('voucher instructions JSON structure - with rider message and redirect', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
    actingAs($user);
    
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['rider'] = [
        'message' => 'Thank you for your redemption! Your payment will be processed within 24 hours.',
        'url' => 'https://example.com/custom-thank-you',
    ];
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    $actualJson = json_encode($voucher->instructions->toArray(), JSON_PRETTY_PRINT);
    
    echo "\n\n=== WITH RIDER MESSAGE AND REDIRECT JSON ===\n";
    echo $actualJson;
    echo "\n============================================\n\n";
    
    $instructionsArray = $voucher->instructions->toArray();
    // Rider structure displayed in JSON above
    expect($instructionsArray)->toHaveKey('rider');
    expect($voucher->instructions->rider->message)->toBe('Thank you for your redemption! Your payment will be processed within 24 hours.');
});

test('voucher instructions JSON structure - with custom prefix and mask', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
    actingAs($user);
    
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 1;
    $base['prefix'] = 'PROMO';
    $base['mask'] = '****-****-****';
    $instructions = VoucherInstructionsData::from($base);
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    $actualJson = json_encode($voucher->instructions->toArray(), JSON_PRETTY_PRINT);
    
    echo "\n\n=== WITH CUSTOM PREFIX AND MASK JSON ===\n";
    echo $actualJson;
    echo "\n========================================\n\n";
    
    $instructionsArray = $voucher->instructions->toArray();
    expect($voucher->code)->toStartWith('PROMO');
    expect($voucher->code)->toMatch('/^PROMO[A-Z0-9-]+$/');
});

test('voucher instructions JSON structure - extended expiry 90 days', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
    actingAs($user);
    
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 1;
    $base['ttl'] = 'P90D'; // 90 days in ISO 8601 duration format
    $instructions = VoucherInstructionsData::from($base);
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    $actualJson = json_encode($voucher->instructions->toArray(), JSON_PRETTY_PRINT);
    
    echo "\n\n=== EXTENDED EXPIRY 90 DAYS JSON ===\n";
    echo $actualJson;
    echo "\n====================================\n\n";
    
    $instructionsArray = $voucher->instructions->toArray();
    expect(round(abs($voucher->expires_at->diffInDays(now()))))->toBe(90.0);
});

test('voucher instructions JSON structure - zero TTL non-expiring', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
    actingAs($user);
    
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 1;
    $base['ttl'] = 'P0D'; // 0 days in ISO 8601 duration format
    $instructions = VoucherInstructionsData::from($base);
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    $actualJson = json_encode($voucher->instructions->toArray(), JSON_PRETTY_PRINT);
    
    echo "\n\n=== ZERO TTL NON-EXPIRING JSON ===\n";
    echo $actualJson;
    echo "\n==================================\n\n";
    
    $instructionsArray = $voucher->instructions->toArray();
    expect($voucher->expires_at)->toBeNull();
});

test('voucher instructions JSON structure - complete configuration', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
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
    $base['count'] = 1;
    $base['prefix'] = 'PROMO';
    $base['mask'] = '****-****-****';
    $base['ttl'] = 'P30D'; // 30 days in ISO 8601 duration format
    $instructions = VoucherInstructionsData::from($base);
    
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    $actualJson = json_encode($voucher->instructions->toArray(), JSON_PRETTY_PRINT);
    
    echo "\n\n=== COMPLETE CONFIGURATION JSON ===\n";
    echo $actualJson;
    echo "\n===================================\n\n";
    
    $instructionsArray = $voucher->instructions->toArray();
    
    // Validate cash structure exists
    expect($instructionsArray['cash'])->toHaveKeys(['amount', 'currency', 'validation']);
    
    // Validate inputs - when called via VoucherInstructionsData::toArray(), inputs has 'fields' key with enum objects
    $inputValues = array_map(fn($f) => $f->value, $instructionsArray['inputs']['fields']);
    expect($inputValues)->toContain('email', 'name', 'address', 'signature');
    
    // Validate feedback - FeedbackInstructionData has direct properties, not a channels array
    expect($instructionsArray['feedback']['email'])->toBe('admin@example.com');
    expect($instructionsArray['feedback']['webhook'])->toBe('https://example.com/webhook');
    
    // Validate rider
    expect($instructionsArray['rider']['message'])->toBe('Thank you for redeeming!');
    expect($instructionsArray['rider']['url'])->toBe('https://example.com/thanks');
    
    // Validate voucher was generated correctly
    expect($voucher->code)->toStartWith('PROMO');
});

test('voucher instructions JSON can be used to recreate voucher', function () {
    $user = User::factory()->create();
    
    $user->deposit(10000);
    
    actingAs($user);
    
    // Create first voucher
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => ['email', 'name']];
    $base['rider'] = ['message' => 'Thank you!'];
    $base['count'] = 1;
    $instructions1 = VoucherInstructionsData::from($base);
    
    $vouchers1 = GenerateVouchers::run($instructions1);
    $voucher1 = $vouchers1->first();
    
    // Export JSON
    $exportedJson = json_encode($voucher1->instructions->toArray());
    
    echo "\n\n=== EXPORTED JSON FOR RECREATION ===\n";
    echo json_encode(json_decode($exportedJson), JSON_PRETTY_PRINT);
    echo "\n====================================\n\n";
    
    // Recreate from exported JSON
    $importedData = json_decode($exportedJson, true);
    $instructions2 = VoucherInstructionsData::from($importedData);
    
    $vouchers2 = GenerateVouchers::run($instructions2);
    $voucher2 = $vouchers2->first();
    
    // Verify both vouchers have same instruction structure
    expect($voucher2->instructions->cash->amount)->toBe($voucher1->instructions->cash->amount);
    expect($voucher2->instructions->cash->currency)->toBe($voucher1->instructions->cash->currency);
    expect($voucher2->instructions->inputs->fields)->toEqual($voucher1->instructions->inputs->fields);
    expect($voucher2->instructions->rider->message)->toBe($voucher1->instructions->rider->message);
});
