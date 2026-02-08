<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use LBHurtado\Contact\Models\Contact;
use LBHurtado\HyperVerge\Actions\LinkKYC\GenerateOnboardingLink;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Initiate KYC verification for a contact.
 *
 * Generates a HyperVerge onboarding link and stores it in the contact record.
 * The contact can then be redirected to this URL to complete KYC verification.
 */
class InitiateContactKYC
{
    use AsAction;

    /**
     * Generate HyperVerge onboarding link for contact.
     *
     * @param  Contact  $contact  The contact to initiate KYC for
     * @param  Voucher  $voucher  The voucher being redeemed (for redirect URL)
     * @return Contact The updated contact with onboarding URL
     */
    public function handle(Contact $contact, Voucher $voucher): Contact
    {
        // Generate unique transaction ID
        $transactionId = "contact_{$contact->id}_".now()->timestamp;

        // Build redirect URL for callback
        $redirectUrl = route('redeem.kyc.callback', [
            'voucher' => $voucher->code,
        ]);

        \Log::info('[InitiateContactKYC] Generating onboarding link', [
            'contact_id' => $contact->id,
            'voucher_code' => $voucher->code,
            'transaction_id' => $transactionId,
            'redirect_url' => $redirectUrl,
        ]);

        // Generate HyperVerge onboarding link
        $onboardingUrl = GenerateOnboardingLink::get(
            transactionId: $transactionId,
            redirectUrl: $redirectUrl
        );

        // Update contact with KYC details
        $contact->update([
            'kyc_transaction_id' => $transactionId,
            'kyc_onboarding_url' => $onboardingUrl,
            'kyc_status' => 'pending',
        ]);

        \Log::info('[InitiateContactKYC] Onboarding link generated', [
            'contact_id' => $contact->id,
            'transaction_id' => $transactionId,
        ]);

        return $contact;
    }
}
