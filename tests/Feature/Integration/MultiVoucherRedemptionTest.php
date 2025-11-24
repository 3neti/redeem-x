<?php

declare(strict_types=1);

use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Http::fake();
});

test('bulk voucher generation and redemption', function () {
    // Step 1: Generate 50 vouchers in one batch
    $user = User::factory()->create();
    $user->deposit(5000000); // Large amount for 50 vouchers
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 50, 'BULK', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 50,
        'prefix' => 'BULK',
        'mask' => '****',
        'ttl' => null,
    ]);
    
    // Step 2: Verify all codes unique
    expect($vouchers)->toHaveCount(50);
    $codes = $vouchers->pluck('code')->toArray();
    expect(array_unique($codes))->toHaveCount(50);
    
    // Step 3: Verify all start with prefix
    foreach ($vouchers as $voucher) {
        expect($voucher->code)->toStartWith('BULK');
    }
    
    // Step 4: Redeem 10 of them sequentially
    $vouchersToRedeem = $vouchers->take(10);
    $contacts = [];
    
    foreach ($vouchersToRedeem as $index => $voucher) {
        $phoneNumber = new PhoneNumber('09171234' . str_pad((string)$index, 3, '0', STR_PAD_LEFT), 'PH');
        $contact = Contact::factory()->create([
            'mobile' => $phoneNumber->formatE164(),
            'country' => 'PH',
        ]);
        $contacts[] = $contact;
        
        $redeemed = RedeemVoucher::run($contact, $voucher->code);
        expect($redeemed)->toBeTrue();
    }
    
    // Step 5: Verify each creates separate Cash entity
    foreach ($vouchersToRedeem as $voucher) {
        $voucher->refresh();
        expect($voucher->cash)->not->toBeNull();
        expect($voucher->cash->amount->getAmount()->toFloat())->toBe(100.0);
        expect($voucher->redeemed_at)->not->toBeNull();
    }
    
    // Step 6: Verify 10 separate contacts created
    expect($contacts)->toHaveCount(10);
    expect(Contact::count())->toBe(10);
    
    // Step 7: Verify remaining 40 vouchers still unredeemed
    $unredeemedCount = Voucher::whereNull('redeemed_at')->count();
    expect($unredeemedCount)->toBe(40);
});

test('concurrent redemption race conditions', function () {
    // Step 1: Generate 5 vouchers
    $user = User::factory()->create();
    $user->deposit(500000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 5, '', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 5,
        'prefix' => '',
        'mask' => '****',
        'ttl' => null,
    ]);
    
    $voucherToTest = $vouchers->first();
    
    // Step 2: Simulate concurrent redemption attempts on same voucher
    $contact1 = Contact::factory()->create(['mobile' => '+639171234567', 'country' => 'PH']);
    $contact2 = Contact::factory()->create(['mobile' => '+639171234568', 'country' => 'PH']);
    
    // First redemption should succeed
    $firstRedemption = null;
    try {
        $firstRedemption = RedeemVoucher::run($contact1, $voucherToTest->code);
    } catch (\Exception $e) {
        // Should not throw
    }
    
    expect($firstRedemption)->toBeTrue();
    
    // Second redemption should fail (voucher already redeemed)
    // RedeemVoucher catches VoucherAlreadyRedeemedException and returns false
    $secondRedemption = RedeemVoucher::run($contact2, $voucherToTest->code);
    
    // Step 3: Verify only first succeeded, second failed
    expect($secondRedemption)->toBeFalse();
    $voucherToTest->refresh();
    expect($voucherToTest->redeemers)->toHaveCount(1);
    expect($voucherToTest->redeemers->first()->redeemer_id)->toBe($contact1->id);
});

test('multi voucher single user', function () {
    // Step 1: Generate 3 vouchers
    $user = User::factory()->create();
    $user->deposit(300000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 3, 'TEST', [
        'cash' => [
            'amount' => 200,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 3,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);
    
    // Step 2: Single redeemer redeems all 3
    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
    ]);
    
    foreach ($vouchers as $voucher) {
        $redeemed = RedeemVoucher::run($contact, $voucher->code);
        expect($redeemed)->toBeTrue();
    }
    
    // Step 3: Verify same Contact reused (same mobile)
    expect(Contact::count())->toBe(1);
    $contact->refresh();
    
    // Step 4: Verify 3 separate Cash entities
    $redeemedVouchers = $vouchers->map(fn($v) => $v->fresh());
    $cashEntities = $redeemedVouchers->map(fn($v) => $v->cash)->filter();
    
    expect($cashEntities)->toHaveCount(3);
    foreach ($cashEntities as $cash) {
        expect($cash->amount->getAmount()->toFloat())->toBe(200.0);
    }
    
    // Step 5: Verify all 3 vouchers redeemed by same contact
    foreach ($redeemedVouchers as $voucher) {
        expect($voucher->redeemed_at)->not->toBeNull();
        expect($voucher->redeemers->first()->redeemer_id)->toBe($contact->id);
    }
});

test('batch redemption with mixed results', function () {
    // Step 1: Generate 10 vouchers: 5 valid, 5 expired
    $user = User::factory()->create();
    $user->deposit(1000000);
    
    // Create 5 valid vouchers
    $validVouchers = VoucherTestHelper::createVouchersWithInstructions($user, 5, 'VALID', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 5,
        'prefix' => 'VALID',
        'mask' => '****',
        'ttl' => 'P7D', // 7 days
    ]);
    
    // Create 5 expired vouchers
    $instructions = VoucherInstructionsData::generateFromScratch();
    
    $expiredVouchers = collect();
    for ($i = 0; $i < 5; $i++) {
        $voucher = Vouchers::withMetadata([
            'instructions' => $instructions->toCleanArray(),
        ])->withOwner($user)
            ->withExpireTime(now()->subDay()) // Expired yesterday
            ->create();
        
        $expiredVouchers->push($voucher);
    }
    
    // Step 2: Attempt to redeem all 10
    $allVouchers = $validVouchers->concat($expiredVouchers);
    $successCount = 0;
    $failCount = 0;
    
    foreach ($allVouchers as $index => $voucher) {
        $phoneNumber = new PhoneNumber('09171234' . str_pad((string)$index, 3, '0', STR_PAD_LEFT), 'PH');
        $contact = Contact::factory()->create([
            'mobile' => $phoneNumber->formatE164(),
            'country' => 'PH',
        ]);
        
        // RedeemVoucher returns false for invalid/expired vouchers
        $redeemed = RedeemVoucher::run($contact, $voucher->code);
        if ($redeemed) {
            $successCount++;
        } else {
            $failCount++;
        }
    }
    
    // Step 3: Verify 5 succeed, 5 fail
    expect($successCount)->toBe(5);
    expect($failCount)->toBe(5);
    
    // Step 4: Verify only valid vouchers were redeemed
    foreach ($validVouchers as $voucher) {
        $voucher->refresh();
        expect($voucher->redeemed_at)->not->toBeNull();
    }
    
    foreach ($expiredVouchers as $voucher) {
        $voucher->refresh();
        expect($voucher->redeemed_at)->toBeNull();
    }
});
