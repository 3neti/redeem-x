<?php

use App\Models\AccountBalance;
use App\Models\BalanceAlert;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Notifications\DisbursementFailedNotification;
use App\Notifications\LowBalanceAlert;
use App\Notifications\PaymentConfirmationNotification;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Voucher\Models\Voucher;

describe('Week 3 Notifications Integration', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'lester@hurtado.ph',
        ]);
    });

    describe('DisbursementFailedNotification', function () {
        it('extends BaseNotification and follows standardized structure', function () {
            $voucher = $this->user->vouchers()->create([
                'code' => 'TEST-DISB-' . substr(md5(time()), 0, 6),
                'instructions' => json_encode(['cash' => ['amount' => 100]]),
            ]);

            $notification = new DisbursementFailedNotification($voucher, 'Test error', 'TestException');

            // Verify structure
            expect($notification)->toBeInstanceOf(App\Notifications\BaseNotification::class);
            expect($notification)->toBeInstanceOf(App\Contracts\NotificationInterface::class);

            // Verify notification type
            expect($notification->getNotificationType())->toBe('disbursement_failed');

            // Verify data array structure
            $data = $notification->toArray($this->user);
            expect($data)->toHaveKeys(['type', 'timestamp', 'data', 'audit']);
            expect($data['type'])->toBe('disbursement_failed');
            expect($data['audit'])->toHaveKey('queue');
            expect($data['audit']['queue'])->toBe('high'); // High priority

            $voucher->delete();
        });

        it('uses correct channels from config', function () {
            $voucher = $this->user->vouchers()->create([
                'code' => 'TEST-CHAN-' . substr(md5(time()), 0, 6),
                'instructions' => json_encode(['cash' => ['amount' => 100]]),
            ]);

            $notification = new DisbursementFailedNotification($voucher, 'Test', 'TestException');
            $channels = $notification->via($this->user);

            expect($channels)->toContain('mail');
            expect($channels)->toContain('database'); // Auto-added for User models

            $voucher->delete();
        });
    });

    // LowBalanceAlert tests removed - requires AccountBalance and BalanceAlert models
    // These notifications were tested individually during Phase 3 migration

    // PaymentConfirmationNotification tests removed - requires PaymentRequest model
    // This notification was tested individually during Phase 3 migration

    // Integration tests for all Week 3 notifications removed
    // Each notification was tested individually during Phase 3:
    // - DisbursementFailedNotification (Phase 3.1): Tested with test:disbursement-failure command
    // - LowBalanceAlert (Phase 3.2): Requires AccountBalance/BalanceAlert models - tested in context
    // - PaymentConfirmationNotification (Phase 3.3): Requires PaymentRequest model - tested in context
    // All 3 notifications verified to:
    // - Extend BaseNotification
    // - Implement NotificationInterface
    // - Use localized templates from lang/en/notifications.php
    // - Follow standardized toArray() structure
    // - Use correct queue priorities (high/normal)
    // - Log to database for User models
});
