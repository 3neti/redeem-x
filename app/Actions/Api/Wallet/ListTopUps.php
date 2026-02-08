<?php

namespace App\Actions\Api\Wallet;

use App\Data\Api\Wallet\TopUpData;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * List Wallet Top-Ups
 *
 * Retrieve a complete history of all wallet top-up transactions with optional status filtering.
 *
 * Returns top-ups in reverse chronological order (newest first). Useful for displaying
 * payment history, reconciliation, and tracking pending payments.
 *
 * **Status Values:**
 * - `PENDING`: Payment initiated, awaiting user completion
 * - `PAID`: Payment successful, wallet credited
 * - `FAILED`: Payment failed or declined
 * - `EXPIRED`: Payment link expired (not completed within time limit)
 *
 * @group Wallet
 *
 * @authenticated
 */
#[Group('Wallet')]
class ListTopUps
{
    /**
     * List wallet top-ups
     *
     * Retrieve your complete top-up transaction history, optionally filtered by payment status.
     *
     * **Response includes** (for each top-up):
     * - `reference_no`: Unique transaction reference
     * - `amount`: Top-up amount requested
     * - `payment_status`: Current status (PENDING, PAID, FAILED, EXPIRED)
     * - `gateway`: Payment gateway used (e.g., "netbank")
     * - `institution_code`: Payment method chosen (GCASH, MAYA, etc.)
     * - `created_at`: When top-up was initiated
     * - `paid_at`: When payment was completed (null if not paid)
     * - `expires_at`: Payment link expiration time
     *
     * Results are sorted by creation date (newest first). Use status filter to show only pending payments or payment history.
     */
    #[QueryParameter('status', description: '*optional* - Filter results by payment status. Valid values: "pending" (awaiting payment), "paid" (successful), "failed" (declined/error), "expired" (payment link expired). Omit to retrieve all top-ups regardless of status. Case-insensitive.', type: 'string', example: 'paid')]
    public function __invoke(Request $request): array
    {
        $status = $request->query('status');

        if ($status && ! in_array(strtoupper($status), ['PENDING', 'PAID', 'FAILED', 'EXPIRED'])) {
            throw ValidationException::withMessages([
                'status' => ['Invalid status. Must be one of: pending, paid, failed, expired'],
            ]);
        }

        $user = $request->user();
        $query = $user->topUps()->latest();

        if ($status) {
            $query->where('payment_status', strtoupper($status));
        }

        $topUps = $query->get()->map(fn ($topUp) => TopUpData::fromModel($topUp));

        return [
            'data' => $topUps,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
                'count' => $topUps->count(),
            ],
        ];
    }
}
