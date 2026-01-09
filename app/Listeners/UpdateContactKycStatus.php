<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Events\DisbursementRequested;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Update Contact KYC status after successful voucher redemption.
 * 
 * Listens to DisbursementRequested event and updates the Contact model
 * with KYC data if it exists in the voucher inputs.
 */
class UpdateContactKycStatus implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(DisbursementRequested $event): void
    {
        $voucher = $event->voucher;
        
        // Get contact from voucher
        $contact = $voucher->contact;
        
        if (!$contact) {
            Log::debug('[UpdateContactKycStatus] No contact found for voucher', [
                'voucher' => $voucher->code,
            ]);
            return;
        }
        
        // Get inputs from voucher
        $inputs = $voucher->inputs->pluck('value', 'name')->toArray();
        
        // Check if KYC data exists (transaction_id and status fields indicate KYC was completed)
        $hasKycData = isset($inputs['transaction_id']) 
            && isset($inputs['status'])
            && (str_contains($inputs['transaction_id'], 'formflow') || str_contains($inputs['transaction_id'], 'MOCK-KYC'));
        
        if (!$hasKycData) {
            Log::debug('[UpdateContactKycStatus] No KYC data found in voucher inputs', [
                'voucher' => $voucher->code,
            ]);
            return;
        }
        
        $kycStatus = $inputs['status'];
        
        if ($kycStatus === 'approved') {
            try {
                $contact->kyc_status = 'approved';
                $contact->kyc_completed_at = now();
                $contact->kyc_transaction_id = $inputs['transaction_id'];
                $contact->save();
                
                Log::info('[UpdateContactKycStatus] Updated Contact KYC status', [
                    'voucher' => $voucher->code,
                    'contact_id' => $contact->id,
                    'mobile' => $contact->mobile,
                    'kyc_status' => 'approved',
                    'transaction_id' => $inputs['transaction_id'],
                ]);
            } catch (\Exception $e) {
                Log::warning('[UpdateContactKycStatus] Failed to update Contact KYC status', [
                    'voucher' => $voucher->code,
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
