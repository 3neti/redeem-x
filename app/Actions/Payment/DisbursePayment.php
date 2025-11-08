<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Disburse payment to redeemer via payment gateway.
 *
 * TODO: Implement actual payment gateway integration
 * This is a stub implementation for Phase 2.
 * Full implementation will come in later phases.
 */
class DisbursePayment
{
    use AsAction;

    /**
     * Disburse payment for redeemed voucher.
     *
     * @param  Voucher  $voucher  The redeemed voucher
     * @param  Contact  $contact  The redeemer
     * @param  array  $bankAccount  Bank account details
     * @return bool  True if successful
     */
    public function handle(Voucher $voucher, Contact $contact, array $bankAccount): bool
    {
        Log::info('[DisbursePayment] Payment disbursement initiated', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'amount' => $voucher->instructions->cash->amount,
            'currency' => $voucher->instructions->cash->currency,
            'bank_code' => $bankAccount['bank_code'] ?? null,
            'account_number' => $bankAccount['account_number'] ?? null,
        ]);

        // TODO: Implement actual payment gateway integration
        // - Use payment-gateway package
        // - Create transaction
        // - Submit to InstaPay/PESONet
        // - Handle callbacks
        // - Update transaction status

        // For now, just log and return success
        Log::info('[DisbursePayment] Payment disbursement completed (stub)', [
            'voucher' => $voucher->code,
            'amount' => $voucher->instructions->cash->amount,
        ]);

        return true;
    }
}
