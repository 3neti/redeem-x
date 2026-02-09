<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Maps form flow collected data to settlement envelope payload and attachments.
 *
 * Form flow steps use step_name keys (e.g., 'wallet_info', 'bio_fields').
 * This service transforms that structure into the envelope's expected format.
 *
 * Note: Form flow collected data may arrive in two formats:
 * 1. Numeric-indexed: [0 => ['_step_name' => 'wallet_info', 'mobile' => '...'], ...]
 * 2. Step-name-keyed: ['wallet_info' => ['mobile' => '...'], ...]
 *
 * This mapper normalizes both formats before extraction.
 */
class FormFlowDataMapper
{
    /**
     * Map collected form flow data to envelope payload structure.
     *
     * @param  array  $collectedData  Form flow collected data (numeric or step-name keyed)
     * @return array Envelope payload structure
     */
    public function toPayload(array $collectedData): array
    {
        // Normalize to step-name keyed format
        $collectedData = $this->normalizeCollectedData($collectedData);

        Log::debug('[FormFlowDataMapper] Normalized data for payload', [
            'keys' => array_keys($collectedData),
        ]);

        $payload = [];

        // Redeemer info from wallet_info and bio_fields
        $redeemer = [];

        // From wallet_info step
        if ($mobile = Arr::get($collectedData, 'wallet_info.mobile')) {
            $redeemer['mobile'] = $mobile;
        }

        // From bio_fields step (try 'full_name' first, fallback to 'name')
        $name = Arr::get($collectedData, 'bio_fields.full_name')
            ?? Arr::get($collectedData, 'bio_fields.name');
        if ($name) {
            $redeemer['name'] = $name;
        }
        if ($birthDate = Arr::get($collectedData, 'bio_fields.birth_date')) {
            $redeemer['birth_date'] = $birthDate;
        }
        if ($address = Arr::get($collectedData, 'bio_fields.address')) {
            $redeemer['address'] = $address;
        }
        if ($email = Arr::get($collectedData, 'bio_fields.email')) {
            $redeemer['email'] = $email;
        }
        if ($referenceCode = Arr::get($collectedData, 'bio_fields.reference_code')) {
            $redeemer['reference_code'] = $referenceCode;
        }
        if ($income = Arr::get($collectedData, 'bio_fields.gross_monthly_income')) {
            $redeemer['gross_monthly_income'] = $income;
        }

        if (! empty($redeemer)) {
            $payload['redeemer'] = $redeemer;
        }

        // Wallet info
        $wallet = [];
        if ($bankCode = Arr::get($collectedData, 'wallet_info.bank_code')) {
            $wallet['bank_code'] = $bankCode;
        }
        if ($accountNumber = Arr::get($collectedData, 'wallet_info.account_number')) {
            $wallet['account_number'] = $accountNumber;
        }
        if ($settlementRail = Arr::get($collectedData, 'wallet_info.settlement_rail')) {
            $wallet['settlement_rail'] = $settlementRail;
        }

        if (! empty($wallet)) {
            $payload['wallet'] = $wallet;
        }

        // KYC info from kyc_verification step
        $kyc = [];
        if ($kycStatus = Arr::get($collectedData, 'kyc_verification.status')) {
            $kyc['status'] = $kycStatus;
        }
        if ($transactionId = Arr::get($collectedData, 'kyc_verification.transaction_id')) {
            $kyc['transaction_id'] = $transactionId;
        }

        if (! empty($kyc)) {
            $payload['kyc'] = $kyc;
        }

        // Location info from location_capture step
        $location = [];
        if ($latitude = Arr::get($collectedData, 'location_capture.latitude')) {
            $location['latitude'] = (float) $latitude;
        }
        if ($longitude = Arr::get($collectedData, 'location_capture.longitude')) {
            $location['longitude'] = (float) $longitude;
        }
        if ($accuracy = Arr::get($collectedData, 'location_capture.accuracy')) {
            $location['accuracy'] = (float) $accuracy;
        }
        // Address can be nested or flat
        $formattedAddress = Arr::get($collectedData, 'location_capture.address.formatted')
            ?? Arr::get($collectedData, 'location_capture.formatted_address');
        if ($formattedAddress) {
            $location['formatted_address'] = $formattedAddress;
        }
        if ($capturedAt = Arr::get($collectedData, 'location_capture.timestamp')) {
            $location['captured_at'] = $capturedAt;
        }

        if (! empty($location)) {
            $payload['location'] = $location;
        }

        return $payload;
    }

    /**
     * Extract attachments from collected form flow data.
     *
     * Returns an array of [doc_type => UploadedFile] for base64 images.
     *
     * @param  array  $collectedData  Form flow collected data (numeric or step-name keyed)
     * @return array<string, UploadedFile> Attachments keyed by document type
     */
    public function extractAttachments(array $collectedData): array
    {
        // Normalize to step-name keyed format
        $collectedData = $this->normalizeCollectedData($collectedData);

        Log::debug('[FormFlowDataMapper] Normalized data for attachments', [
            'keys' => array_keys($collectedData),
        ]);

        $attachments = [];

        // Selfie from selfie_capture step
        if ($selfieBase64 = Arr::get($collectedData, 'selfie_capture.selfie')) {
            $file = $this->base64ToUploadedFile($selfieBase64, 'selfie.jpg', 'image/jpeg');
            if ($file) {
                $attachments['SELFIE'] = $file;
            }
        }

        // Signature from signature_capture step
        if ($signatureBase64 = Arr::get($collectedData, 'signature_capture.signature')) {
            $file = $this->base64ToUploadedFile($signatureBase64, 'signature.png', 'image/png');
            if ($file) {
                $attachments['SIGNATURE'] = $file;
            }
        }

        // Map snapshot from location_capture step
        if ($mapBase64 = Arr::get($collectedData, 'location_capture.map')) {
            $file = $this->base64ToUploadedFile($mapBase64, 'map_snapshot.png', 'image/png');
            if ($file) {
                $attachments['MAP_SNAPSHOT'] = $file;
            }
        }

        // KYC images (if available from HyperVerge)
        if ($idFront = Arr::get($collectedData, 'kyc_verification.id_front')) {
            $file = $this->base64ToUploadedFile($idFront, 'kyc_id_front.jpg', 'image/jpeg');
            if ($file) {
                $attachments['KYC_ID_FRONT'] = $file;
            }
        }
        if ($idBack = Arr::get($collectedData, 'kyc_verification.id_back')) {
            $file = $this->base64ToUploadedFile($idBack, 'kyc_id_back.jpg', 'image/jpeg');
            if ($file) {
                $attachments['KYC_ID_BACK'] = $file;
            }
        }
        if ($kycSelfie = Arr::get($collectedData, 'kyc_verification.kyc_selfie')) {
            $file = $this->base64ToUploadedFile($kycSelfie, 'kyc_selfie.jpg', 'image/jpeg');
            if ($file) {
                $attachments['KYC_SELFIE'] = $file;
            }
        }

        return $attachments;
    }

    /**
     * Convert base64 encoded image to UploadedFile.
     *
     * @param  string  $base64  Base64 encoded image (with or without data URI prefix)
     * @param  string  $filename  Desired filename
     * @param  string  $mimeType  MIME type
     */
    protected function base64ToUploadedFile(string $base64, string $filename, string $mimeType): ?UploadedFile
    {
        try {
            // Remove data URI prefix if present (e.g., "data:image/jpeg;base64,")
            if (str_contains($base64, ',')) {
                $base64 = explode(',', $base64, 2)[1];
            }

            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                Log::warning('[FormFlowDataMapper] Failed to decode base64', [
                    'filename' => $filename,
                ]);

                return null;
            }

            // Write to temp file
            $tempPath = sys_get_temp_dir().'/'.uniqid('formflow_', true).'_'.$filename;
            file_put_contents($tempPath, $decoded);

            return new UploadedFile(
                path: $tempPath,
                originalName: $filename,
                mimeType: $mimeType,
                error: UPLOAD_ERR_OK,
                test: true // Mark as test to skip is_uploaded_file check
            );
        } catch (\Throwable $e) {
            Log::warning('[FormFlowDataMapper] Failed to convert base64 to file', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Normalize collected data to step-name keyed format.
     *
     * Form flow may return data in two formats:
     * 1. Numeric-indexed: [0 => ['_step_name' => 'wallet_info', ...], 1 => [...]]
     * 2. Step-name-keyed: ['wallet_info' => [...], 'bio_fields' => [...]]
     *
     * This method converts format 1 to format 2 for consistent access.
     *
     * @param  array  $collectedData  Raw collected data from form flow
     * @return array Normalized data keyed by step_name
     */
    protected function normalizeCollectedData(array $collectedData): array
    {
        // If already keyed by step names (string keys), return as-is
        $firstKey = array_key_first($collectedData);
        if ($firstKey !== null && is_string($firstKey) && ! is_numeric($firstKey)) {
            return $collectedData;
        }

        // Convert numeric-indexed to step-name-keyed
        $normalized = [];
        foreach ($collectedData as $stepData) {
            if (! is_array($stepData)) {
                continue;
            }

            // Extract step_name from the data (stored as '_step_name' field)
            $stepName = $stepData['_step_name'] ?? null;
            if (! $stepName) {
                // Try without underscore prefix
                $stepName = $stepData['step_name'] ?? null;
            }

            if ($stepName) {
                // Remove the _step_name field from the data itself
                unset($stepData['_step_name'], $stepData['step_name']);
                $normalized[$stepName] = $stepData;
            }
        }

        return $normalized;
    }
}
