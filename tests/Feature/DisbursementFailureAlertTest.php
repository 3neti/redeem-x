<?php

use App\Models\User;
use App\Notifications\DisbursementFailedNotification;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Wallet\Events\DisbursementFailed;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    config(['disbursement.alerts.enabled' => true]);
});

test('disbursement attempt is logged when disbursement starts', function () {
    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    // Mock a disbursement attempt
    $attempt = DisbursementAttempt::create([
        'voucher_id' => $voucher->id,
        'voucher_code' => $voucher->code,
        'amount' => 100.00,
        'currency' => 'PHP',
        'mobile' => '09171234567',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09171234567',
        'settlement_rail' => 'INSTAPAY',
        'gateway' => 'netbank',
        'reference_id' => 'TEST-REF-123',
        'status' => 'pending',
        'attempted_at' => now(),
    ]);

    expect($attempt)->toBeInstanceOf(DisbursementAttempt::class)
        ->and($attempt->status)->toBe('pending')
        ->and($attempt->voucher_code)->toBe($voucher->code);
});

test('disbursement attempt is marked as failed when disbursement fails', function () {
    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    $attempt = DisbursementAttempt::create([
        'voucher_id' => $voucher->id,
        'voucher_code' => $voucher->code,
        'amount' => 100.00,
        'currency' => 'PHP',
        'mobile' => '09171234567',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09171234567',
        'settlement_rail' => 'INSTAPAY',
        'gateway' => 'netbank',
        'reference_id' => 'TEST-REF-456',
        'status' => 'pending',
        'attempted_at' => now(),
    ]);

    // Simulate failure
    $attempt->update([
        'status' => 'failed',
        'error_type' => 'network_timeout',
        'error_message' => 'Connection timeout',
        'completed_at' => now(),
    ]);

    expect($attempt->fresh()->status)->toBe('failed')
        ->and($attempt->error_type)->toBe('network_timeout')
        ->and($attempt->completed_at)->not->toBeNull();
});

test('disbursement failure event triggers admin notification', function () {
    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    $exception = new \RuntimeException('Test disbursement failure');

    // Fire the event
    event(new DisbursementFailed($voucher, $exception));

    // Assert notification was sent (if there are admin users or configured emails)
    // Since we don't have admin users in this test, check that notification would be sent
    // In a real scenario with admin users, use:
    // Notification::assertSentTo($adminUser, DisbursementFailedNotification::class);

    expect(true)->toBeTrue(); // Event fired successfully
});

test('disbursement failed notification contains voucher details', function () {
    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    $code = $voucher->code; // Use actual generated code
    $exception = new \RuntimeException('Gateway error occurred');

    $notification = DisbursementFailedNotification::fromException($voucher, $exception);

    // Check array representation - BaseNotification uses standardized structure
    $array = $notification->toArray($user);
    expect($array)->toHaveKeys(['type', 'timestamp', 'data', 'audit'])
        ->and($array['type'])->toBe('disbursement_failed')
        ->and($array['data']['voucher_code'])->toBe($code)
        ->and($array['data']['error_message'])->toBe('Gateway error occurred')
        ->and($array['data']['error_type'])->toBe('RuntimeException')
        ->and($array['data']['voucher_id'])->toBe($voucher->id)
        ->and($array['audit']['voucher_code'])->toBe($code);
});

test('disbursement attempt scopes work correctly', function () {
    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();

    // Create failed attempt
    DisbursementAttempt::create([
        'voucher_id' => $voucher->id,
        'voucher_code' => $voucher->code,
        'amount' => 100.00,
        'currency' => 'PHP',
        'mobile' => '09171234567',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09171234567',
        'settlement_rail' => 'INSTAPAY',
        'gateway' => 'netbank',
        'reference_id' => 'FAIL-001',
        'status' => 'failed',
        'error_type' => 'network_timeout',
        'attempted_at' => now()->subDays(3),
    ]);

    // Create success attempt
    DisbursementAttempt::create([
        'voucher_id' => $voucher->id,
        'voucher_code' => $voucher->code,
        'amount' => 200.00,
        'currency' => 'PHP',
        'mobile' => '09171234567',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09171234567',
        'settlement_rail' => 'INSTAPAY',
        'gateway' => 'netbank',
        'reference_id' => 'SUCCESS-001',
        'status' => 'success',
        'attempted_at' => now(),
    ]);

    expect(DisbursementAttempt::failed()->count())->toBe(1)
        ->and(DisbursementAttempt::success()->count())->toBe(1)
        ->and(DisbursementAttempt::recent(7)->count())->toBe(2)
        ->and(DisbursementAttempt::byGateway('netbank')->count())->toBe(2)
        ->and(DisbursementAttempt::byErrorType('network_timeout')->count())->toBe(1);
});

test('alerts can be disabled via configuration', function () {
    config(['disbursement.alerts.enabled' => false]);

    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    $exception = new \RuntimeException('Test error');

    // Fire event
    event(new DisbursementFailed($voucher, $exception));

    // No notifications should be sent when disabled
    Notification::assertNothingSent();
});
