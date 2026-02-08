<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Voucher Redeemed Via Messaging Event
 *
 * Fired when a voucher is redeemed through any messaging channel
 * (SMS, Viber, Messenger, WhatsApp, etc.)
 *
 * This event has no listeners yet - it's for future extensibility:
 * - Analytics tracking
 * - Custom business logic hooks
 * - Audit logging
 * - Third-party integrations
 */
class VoucherRedeemedViaMessaging
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Voucher $voucher,
        public Contact $contact,
        public string $channel,        // 'sms', 'viber', 'messenger', 'whatsapp', etc.
        public string $bankAccount,    // Resolved bank account (e.g., 'GCASH:639173011987')
        public array $messageMetadata = [] // Original message data for audit trail
    ) {}
}
