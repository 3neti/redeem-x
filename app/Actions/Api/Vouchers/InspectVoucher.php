<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Number;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Dedoc\Scramble\Attributes\Group;

/**
 * @group Vouchers
 *
 * Inspect voucher metadata ("x-ray" endpoint).
 * 
 * Public endpoint (no auth) that returns voucher metadata and instructions for transparency.
 * Useful for users to verify voucher origin, issuer, licenses, redemption options, and requirements.
 */
#[Group('Vouchers')]
class InspectVoucher
{
    use AsAction;

    public function handle(string $code): JsonResponse
    {
        // Find voucher by code
        $voucher = Voucher::where('code', $code)->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found',
            ], 404);
        }

        // Extract metadata from voucher instructions
        $instructions = $voucher->metadata['instructions'] ?? [];
        $metadata = $instructions['metadata'] ?? null;

        // Build response
        $response = [
            'success' => true,
            'code' => $voucher->code,
            'status' => $this->getVoucherStatus($voucher),
            'metadata' => $metadata,
        ];

        // Include basic voucher info (non-sensitive)
        if ($metadata) {
            $response['info'] = [
                'version' => $metadata['version'] ?? null,
                'system_name' => $metadata['system_name'] ?? null,
                'copyright' => $metadata['copyright'] ?? null,
                'licenses' => $metadata['licenses'] ?? [],
                'issuer' => [
                    'name' => $metadata['issuer_name'] ?? null,
                    'email' => $metadata['issuer_email'] ?? null,
                ],
                'redemption_urls' => $metadata['redemption_urls'] ?? [],
                'primary_url' => $metadata['primary_url'] ?? null,
                'issued_at' => $metadata['issued_at'] ?? null,
            ];
        } else {
            $response['info'] = [
                'message' => 'This voucher was created before metadata tracking was implemented.',
            ];
        }

        // Determine preview policy from metadata (defaults: enabled=true, scope='full')
        $previewEnabled = true;
        $previewScope = 'full';
        $previewMessage = null;
        if (is_array($metadata)) {
            $previewEnabled = array_key_exists('preview_enabled', $metadata) ? (bool)$metadata['preview_enabled'] : true;
            $previewScope = $metadata['preview_scope'] ?? 'full';
            $previewMessage = $metadata['preview_message'] ?? null;
        }

        $response['preview'] = [
            'enabled' => $previewEnabled && $previewScope !== 'none',
            'scope' => $previewEnabled ? $previewScope : 'none',
            'message' => $previewMessage,
        ];

        // Add instructions data obeying preview policy
        if (isset($voucher->metadata['instructions'])) {
            try {
                if ($response['preview']['enabled'] === false) {
                    // No instructions exposed
                } elseif ($response['preview']['scope'] === 'requirements_only') {
                    $full = $this->formatInstructions($voucher);
                    $response['instructions'] = $this->filterRequirementsOnly($full);
                } else { // full
                    $response['instructions'] = $this->formatInstructions($voucher);
                }
            } catch (\Exception $e) {
                \Log::warning('[InspectVoucher] Failed to format instructions', [
                    'code' => $voucher->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json($response);
    }

    /**
     * Format instructions for public display (masks sensitive data).
     */
    private function formatInstructions(Voucher $voucher): array
    {
        // Parse instructions from metadata (accessor may fail for some vouchers)
        $instructions = VoucherInstructionsData::from($voucher->metadata['instructions']);

        // Format amount
        $formattedAmount = Number::currency(
            $instructions->cash->amount,
            $instructions->cash->currency
        );

        // Get required inputs with labels
        $requiredInputs = [];
        if ($instructions->inputs && $instructions->inputs->fields) {
            foreach ($instructions->inputs->fields as $field) {
                $requiredInputs[] = [
                    'value' => $field->value,
                    'label' => $this->getInputFieldLabel($field),
                ];
            }
        }

        // Build instructions response
        $response = [
            'amount' => $instructions->cash->amount,
            'currency' => $instructions->cash->currency,
            'formatted_amount' => $formattedAmount,
            'required_inputs' => $requiredInputs,
            'expires_at' => $voucher->expires_at?->toISOString(),
            'starts_at' => $voucher->starts_at?->toISOString(),
        ];

        // Add validation info (mask sensitive data)
        $response['validation'] = [
            'has_secret' => !empty($instructions->cash->validation->secret),
            'is_assigned' => !empty($instructions->cash->validation->mobile),
            'assigned_mobile_masked' => $this->maskMobile($instructions->cash->validation->mobile),
        ];

        // Add location validation if present
        if ($instructions->validation?->location) {
            $location = $instructions->validation->location;
            $response['location_validation'] = [
                'required' => $location->required,
                'target_lat' => $location->target_lat,
                'target_lng' => $location->target_lng,
                'radius_meters' => $location->radius_meters,
                'on_failure' => $location->on_failure,
                'description' => $this->formatLocationDescription($location),
            ];
        }

        // Add time validation if present
        if ($instructions->validation?->time) {
            $time = $instructions->validation->time;
            $response['time_validation'] = [
                'window' => $time->window ? [
                    'start_time' => $time->window->start_time,
                    'end_time' => $time->window->end_time,
                    'timezone' => $time->window->timezone,
                ] : null,
                'limit_minutes' => $time->limit_minutes,
                'description' => $this->formatTimeDescription($time),
            ];
        }

        // Add rider info if present
        if ($instructions->rider && ($instructions->rider->message || $instructions->rider->url)) {
            $response['rider'] = [
                'message' => $instructions->rider->message,
                'url' => $instructions->rider->url,
            ];
        }

        return $response;
    }

    /**
     * Filter instructions to requirements-only scope.
     */
    private function filterRequirementsOnly(array $full): array
    {
        // Only keep non-sensitive fields helpful for preparation
        $allowed = [
            'required_inputs',
            'validation',
            'location_validation',
            'time_validation',
            'starts_at',
            'expires_at',
            'rider',
        ];

        $filtered = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $full)) {
                $filtered[$key] = $full[$key];
            }
        }

        // Ensure amount/currency are removed
        unset($filtered['amount'], $filtered['currency'], $filtered['formatted_amount']);
        return $filtered;
    }

    /**
     * Get friendly label for input field.
     */
    private function getInputFieldLabel(VoucherInputField $field): string
    {
        return match ($field) {
            VoucherInputField::NAME => 'Full Name',
            VoucherInputField::EMAIL => 'Email Address',
            VoucherInputField::MOBILE => 'Mobile Number',
            VoucherInputField::REFERENCE_CODE => 'Reference Code',
            VoucherInputField::SIGNATURE => 'Signature',
            VoucherInputField::ADDRESS => 'Residential Address',
            VoucherInputField::BIRTH_DATE => 'Birth Date',
            VoucherInputField::GROSS_MONTHLY_INCOME => 'Gross Monthly Income',
            VoucherInputField::LOCATION => 'Location',
            VoucherInputField::OTP => 'OTP',
            VoucherInputField::SELFIE => 'Selfie Photo',
            VoucherInputField::KYC => 'Identity Verification (KYC)',
            default => $field->value,
        };
    }

    /**
     * Mask mobile number for privacy (show country code + last 2 digits).
     */
    private function maskMobile(?string $mobile): ?string
    {
        if (!$mobile) {
            return null;
        }

        // Extract country code and last 2 digits
        // Example: +639171234567 -> +639XXXXXXX67
        $length = strlen($mobile);
        if ($length < 5) {
            return str_repeat('X', $length);
        }

        $countryCode = substr($mobile, 0, 3); // +63
        $lastTwo = substr($mobile, -2); // 67
        $middleLength = $length - 5; // Length of middle part to mask

        return $countryCode . str_repeat('X', $middleLength) . $lastTwo;
    }

    /**
     * Format location validation description.
     */
    private function formatLocationDescription($location): string
    {
        $action = $location->on_failure === 'block' ? 'Required' : 'Warning only';
        return "Must be within {$location->radius_meters}m of coordinates ({$location->target_lat}, {$location->target_lng}). {$action}.";
    }

    /**
     * Format time validation description.
     */
    private function formatTimeDescription($time): string
    {
        $descriptions = [];

        if ($time->window) {
            $descriptions[] = "Can only be redeemed between {$time->window->start_time} - {$time->window->end_time} ({$time->window->timezone})";
        }

        if ($time->limit_minutes) {
            $descriptions[] = "Must complete within {$time->limit_minutes} minutes of starting";
        }

        return implode('. ', $descriptions) . '.';
    }

    /**
     * Get human-readable voucher status.
     */
    private function getVoucherStatus(Voucher $voucher): string
    {
        if ($voucher->redeemed_at) {
            return 'redeemed';
        }

        if ($voucher->isExpired()) {
            return 'expired';
        }

        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            return 'scheduled';
        }

        return 'active';
    }

    /**
     * Inspect voucher metadata and instructions
     * 
     * Public endpoint that reveals voucher origin, issuer, requirements, and redemption options.
     * Useful for transparency and verification before redemption.
     */
    public function asController(string $code): JsonResponse
    {
        return $this->handle($code);
    }
}
