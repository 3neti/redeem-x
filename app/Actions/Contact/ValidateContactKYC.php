<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use LBHurtado\Contact\Models\Contact;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Validate if a contact has approved KYC verification.
 * 
 * This is a simple check to see if the contact's KYC status is approved.
 * Can be extended in the future to check expiry dates, etc.
 */
class ValidateContactKYC
{
    use AsAction;

    /**
     * Check if contact has valid KYC approval.
     * 
     * @param  Contact  $contact  The contact to validate
     * @return bool  True if KYC is approved
     */
    public function handle(Contact $contact): bool
    {
        $isApproved = $contact->isKycApproved();

        \Log::debug('[ValidateContactKYC] KYC validation check', [
            'contact_id' => $contact->id,
            'kyc_status' => $contact->kyc_status,
            'is_approved' => $isApproved,
        ]);

        return $isApproved;
    }
}
