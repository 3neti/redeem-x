<?php

declare(strict_types=1);

use App\Actions\Contact\FetchContactKYCResult;
use App\Actions\Contact\InitiateContactKYC;
use App\Actions\Contact\ValidateContactKYC;
use App\Actions\Voucher\ProcessRedemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\HyperVerge\Actions\LinkKYC\GenerateOnboardingLink;
use LBHurtado\HyperVerge\Actions\Results\FetchKYCResult;
use LBHurtado\HyperVerge\Actions\Results\ValidateKYCResult;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock HyperVerge API calls
    Http::fake([
        'ind.idv.hyperverge.co/*' => Http::response([
            'status' => 'success',
            'result' => [
                'summary' => [
                    'action' => 'pass',
                    'details' => [],
                ],
            ],
        ], 200),
    ]);
});

test('contact KYC attributes are stored as schemaless attributes', function () {
    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
        'country' => 'PH',
    ]);

    // Set KYC attributes
    $contact->update([
        'kyc_transaction_id' => 'test_txn_123',
        'kyc_status' => 'approved',
        'kyc_onboarding_url' => 'https://hyperverge.co/test',
        'kyc_submitted_at' => now()->toISOString(),
        'kyc_completed_at' => now()->toISOString(),
        'kyc_rejection_reasons' => ['reason1', 'reason2'],
    ]);

    // Verify attributes are accessible
    expect($contact->kyc_transaction_id)->toBe('test_txn_123');
    expect($contact->kyc_status)->toBe('approved');
    expect($contact->kyc_onboarding_url)->toBe('https://hyperverge.co/test');
    expect($contact->kyc_rejection_reasons)->toBe(['reason1', 'reason2']);

    // Verify stored in meta (schemaless)
    expect($contact->meta)->not->toBeNull();
    expect($contact->meta->get('kyc_transaction_id'))->toBe('test_txn_123');
    expect($contact->meta->get('kyc_status'))->toBe('approved');
});

test('isKycApproved returns true for approved contacts', function () {
    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
    ]);

    expect($contact->isKycApproved())->toBeFalse();

    $contact->update(['kyc_status' => 'approved']);
    
    expect($contact->isKycApproved())->toBeTrue();
});

test('needsKyc returns true when KYC not approved', function () {
    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
    ]);

    expect($contact->needsKyc())->toBeTrue();

    $contact->update(['kyc_status' => 'approved']);
    
    expect($contact->needsKyc())->toBeFalse();
});

test('InitiateContactKYC generates onboarding link', function () {
    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => ['kyc']], // KYC required
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();
    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
    ]);

    // Mock GenerateOnboardingLink
    $this->partialMock(GenerateOnboardingLink::class, function ($mock) {
        $mock->shouldReceive('get')
            ->once()
            ->andReturn('https://hyperverge.co/onboard/test_123');
    });

    $result = InitiateContactKYC::run($contact, $voucher);

    expect($result->kyc_transaction_id)->toContain('contact_');
    expect($result->kyc_onboarding_url)->toBe('https://hyperverge.co/onboard/test_123');
    expect($result->kyc_status)->toBe('pending');
});

test('ValidateContactKYC returns true for approved contacts', function () {
    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
        'kyc_status' => 'approved',
    ]);

    $isValid = ValidateContactKYC::run($contact);

    expect($isValid)->toBeTrue();
});

test('ValidateContactKYC returns false for non-approved contacts', function () {
    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
        'kyc_status' => 'pending',
    ]);

    $isValid = ValidateContactKYC::run($contact);

    expect($isValid)->toBeFalse();
});

test('FetchContactKYCResult updates contact with approved status', function () {
    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
        'kyc_transaction_id' => 'test_txn_123',
        'kyc_status' => 'processing',
    ]);

    // Mock FetchKYCResult and ValidateKYCResult
    $this->partialMock(FetchKYCResult::class, function ($mock) {
        $mock->shouldReceive('run')->once()->andReturn((object)['status' => 'success']);
    });

    $this->partialMock(ValidateKYCResult::class, function ($mock) {
        $mock->shouldReceive('run')->once()->andReturn((object)[
            'valid' => true,
            'reasons' => [],
        ]);
    });

    $result = FetchContactKYCResult::run($contact);

    expect($result->kyc_status)->toBe('approved');
    expect($result->kyc_completed_at)->not->toBeNull();
    expect($result->kyc_rejection_reasons)->toBeNull();
});

test('FetchContactKYCResult updates contact with rejected status', function () {
    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
        'kyc_transaction_id' => 'test_txn_123',
        'kyc_status' => 'processing',
    ]);

    // Mock FetchKYCResult and ValidateKYCResult with rejection
    $this->partialMock(FetchKYCResult::class, function ($mock) {
        $mock->shouldReceive('run')->once()->andReturn((object)['status' => 'failed']);
    });

    $this->partialMock(ValidateKYCResult::class, function ($mock) {
        $mock->shouldReceive('run')->once()->andReturn((object)[
            'valid' => false,
            'reasons' => ['Face mismatch', 'ID expired'],
        ]);
    });

    $result = FetchContactKYCResult::run($contact);

    expect($result->kyc_status)->toBe('rejected');
    expect($result->kyc_completed_at)->not->toBeNull();
    expect($result->kyc_rejection_reasons)->toBe(['Face mismatch', 'ID expired']);
});

test('redemption blocked when KYC required but not approved', function () {
    $user = User::factory()->create();
    $user->deposit(100000);

    // Create voucher with KYC requirement
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => ['kyc']], // KYC required
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();
    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
        'kyc_status' => 'pending', // Not approved
    ]);

    // Attempt redemption - should throw exception
    expect(fn() => ProcessRedemption::run($voucher, $phoneNumber, [], []))
        ->toThrow(RuntimeException::class, 'Identity verification required');
});

test('redemption succeeds when KYC required and approved', function () {
    $user = User::factory()->create();
    $user->deposit(100000);

    // Create voucher with KYC requirement
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => ['kyc']], // KYC required
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();
    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
        'kyc_status' => 'approved', // KYC approved
    ]);

    // Attempt redemption - should succeed
    $result = ProcessRedemption::run($voucher, $phoneNumber, [], []);

    expect($result)->toBeTrue();
    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();
});

test('redemption succeeds when KYC not required', function () {
    $user = User::factory()->create();
    $user->deposit(100000);

    // Create voucher WITHOUT KYC requirement
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => ['email']], // No KYC
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();
    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
        // No KYC status - should not matter
    ]);

    // Attempt redemption - should succeed without KYC
    $result = ProcessRedemption::run($voucher, $phoneNumber, [], []);

    expect($result)->toBeTrue();
    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();
});

test('complete KYC redemption flow end-to-end', function () {
    // Step 1: Generate voucher with KYC requirement
    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
        'cash' => [
            'amount' => 500,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => ['kyc', 'email', 'name']],
        'feedback' => ['email' => 'admin@example.com', 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => 'Thank you!', 'url' => null],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****-****',
        'ttl' => 'P7D',
    ]);

    $voucher = $vouchers->first();
    expect(in_array('kyc', $voucher->instructions->inputs->fields ?? []))->toBeTrue();

    // Step 2: Create contact and initiate KYC
    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
    ]);

    // Mock KYC link generation
    $this->partialMock(GenerateOnboardingLink::class, function ($mock) {
        $mock->shouldReceive('get')->once()->andReturn('https://hyperverge.co/onboard/test');
    });

    $contact = InitiateContactKYC::run($contact, $voucher);
    expect($contact->kyc_status)->toBe('pending');
    expect($contact->kyc_transaction_id)->toContain('contact_');

    // Step 3: Simulate KYC completion (mock HyperVerge approval)
    $this->partialMock(FetchKYCResult::class, function ($mock) {
        $mock->shouldReceive('run')->once()->andReturn((object)['status' => 'success']);
    });

    $this->partialMock(ValidateKYCResult::class, function ($mock) {
        $mock->shouldReceive('run')->once()->andReturn((object)[
            'valid' => true,
            'reasons' => [],
        ]);
    });

    $contact = FetchContactKYCResult::run($contact);
    expect($contact->kyc_status)->toBe('approved');

    // Step 4: Redeem voucher with approved KYC
    $result = ProcessRedemption::run(
        $voucher,
        $phoneNumber,
        ['email' => 'redeemer@example.com', 'name' => 'John Doe'],
        ['bank_code' => 'GCASH', 'account_number' => '09171234567']
    );

    expect($result)->toBeTrue();
    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect($voucher->redeemers)->toHaveCount(1);

    // Step 5: Verify contact KYC remains approved for future redemptions
    $contact->refresh();
    expect($contact->isKycApproved())->toBeTrue();
});

test('KYC reused across multiple voucher redemptions', function () {
    $user = User::factory()->create();
    $user->deposit(100000);

    // Create 2 vouchers with KYC requirement
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 2, 'TEST', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => ['kyc']],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 2,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);

    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
        'kyc_status' => 'approved', // Already approved from previous KYC
    ]);

    // Redeem first voucher - should succeed
    $result1 = ProcessRedemption::run($vouchers[0], $phoneNumber, [], []);
    expect($result1)->toBeTrue();

    // Redeem second voucher - should succeed without re-doing KYC
    $result2 = ProcessRedemption::run($vouchers[1], $phoneNumber, [], []);
    expect($result2)->toBeTrue();

    // Both vouchers redeemed with same approved KYC
    expect($vouchers[0]->fresh()->redeemed_at)->not->toBeNull();
    expect($vouchers[1]->fresh()->redeemed_at)->not->toBeNull();
});
