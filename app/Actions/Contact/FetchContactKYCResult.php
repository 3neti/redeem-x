<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use LBHurtado\Contact\Models\Contact;
use LBHurtado\HyperVerge\Actions\Results\FetchKYCResult;
use LBHurtado\HyperVerge\Actions\Results\ProcessKYCData;
use LBHurtado\HyperVerge\Actions\Results\StoreKYCImages;
use LBHurtado\HyperVerge\Actions\Results\ValidateKYCResult;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fetch and process KYC results from HyperVerge.
 *
 * This action retrieves the KYC verification results, validates them,
 * stores images if approved, and updates the contact status.
 */
class FetchContactKYCResult
{
    use AsAction;

    /**
     * Retrieve and process KYC results from HyperVerge.
     *
     * @param  Contact  $contact  The contact to fetch results for
     * @return Contact The updated contact
     *
     * @throws \Exception If no transaction ID is set
     */
    public function handle(Contact $contact): Contact
    {
        if (! $contact->kyc_transaction_id) {
            throw new \Exception('Contact has no KYC transaction ID');
        }

        \Log::info('[FetchContactKYCResult] Fetching KYC results', [
            'contact_id' => $contact->id,
            'transaction_id' => $contact->kyc_transaction_id,
        ]);

        try {
            // Step 1: Fetch KYC result from HyperVerge
            $result = FetchKYCResult::run($contact->kyc_transaction_id);

            // Step 2: Validate result
            $validation = ValidateKYCResult::run($contact->kyc_transaction_id);

            if ($validation->valid) {
                // KYC approved - store images and process data
                \Log::info('[FetchContactKYCResult] KYC approved', [
                    'contact_id' => $contact->id,
                    'transaction_id' => $contact->kyc_transaction_id,
                ]);

                // Extract and store images (async job would be better for production)
                try {
                    $imageUrls = \LBHurtado\HyperVerge\Actions\Results\ExtractKYCImages::run($contact->kyc_transaction_id);
                    if (! empty($imageUrls)) {
                        StoreKYCImages::run($contact, $imageUrls, $contact->kyc_transaction_id);
                    }
                } catch (\Exception $e) {
                    \Log::warning('[FetchContactKYCResult] Failed to store images', [
                        'contact_id' => $contact->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue even if image storage fails
                }

                // Process KYC data (extract name, DOB, address, etc.)
                try {
                    ProcessKYCData::run($contact, $contact->kyc_transaction_id, includeAddress: true);
                } catch (\Exception $e) {
                    \Log::warning('[FetchContactKYCResult] Failed to process KYC data', [
                        'contact_id' => $contact->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue even if data processing fails
                }

                // Update contact status to approved
                $contact->update([
                    'kyc_status' => 'approved',
                    'kyc_completed_at' => now(),
                    'kyc_rejection_reasons' => null,
                ]);
            } else {
                // KYC rejected
                \Log::warning('[FetchContactKYCResult] KYC rejected', [
                    'contact_id' => $contact->id,
                    'transaction_id' => $contact->kyc_transaction_id,
                    'reasons' => $validation->reasons,
                ]);

                $contact->update([
                    'kyc_status' => 'rejected',
                    'kyc_completed_at' => now(),
                    'kyc_rejection_reasons' => $validation->reasons,
                ]);
            }
        } catch (\Exception $e) {
            // If we can't fetch results yet, it might still be processing
            \Log::info('[FetchContactKYCResult] Results not ready yet', [
                'contact_id' => $contact->id,
                'transaction_id' => $contact->kyc_transaction_id,
                'error' => $e->getMessage(),
            ]);

            // Keep status as processing if it was pending
            if ($contact->kyc_status === 'pending') {
                $contact->update(['kyc_status' => 'processing']);
            }

            throw $e;
        }

        return $contact->fresh();
    }
}
