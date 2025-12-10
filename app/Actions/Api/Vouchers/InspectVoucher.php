<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Inspect voucher metadata ("x-ray" endpoint).
 * 
 * Public endpoint (no auth) that returns voucher metadata for transparency.
 * Useful for users to verify voucher origin, issuer, licenses, and redemption options.
 */
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

        return response()->json($response);
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
     * Handle as controller (for route binding).
     */
    public function asController(string $code): JsonResponse
    {
        return $this->handle($code);
    }
}
