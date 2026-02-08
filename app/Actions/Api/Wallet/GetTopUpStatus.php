<?php

namespace App\Actions\Api\Wallet;

use App\Data\Api\Wallet\TopUpData;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\Request;
use LBHurtado\PaymentGateway\Exceptions\TopUpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Get Top-Up Status
 *
 * Check the current payment status of a specific wallet top-up transaction by reference number.
 *
 * Use this endpoint to:
 * - Poll for payment completion after redirecting user to payment gateway
 * - Verify if a pending payment has been completed
 * - Retrieve payment details for customer support or reconciliation
 *
 * **Recommended Polling Strategy:**
 * - Poll every 3-5 seconds while user is on callback/status page
 * - Stop polling once status changes from PENDING to PAID/FAILED/EXPIRED
 * - Set a maximum polling duration (e.g., 30 minutes)
 *
 * @group Wallet
 *
 * @authenticated
 */
#[Group('Wallet')]
class GetTopUpStatus
{
    /**
     * Get top-up status
     *
     * Retrieve the current payment status and details of a specific top-up transaction.
     *
     * **Response includes:**
     * - `reference_no`: Transaction reference number
     * - `amount`: Top-up amount
     * - `payment_status`: Current status (PENDING, PAID, FAILED, EXPIRED)
     * - `payment_url`: Original payment URL (null if expired)
     * - `gateway`: Payment gateway used
     * - `institution_code`: Payment method selected
     * - `created_at`: When top-up was initiated
     * - `paid_at`: Payment completion timestamp (null if not paid)
     * - `expires_at`: Payment link expiration
     * - `error_message`: Error details if status is FAILED
     *
     * **Common Use Cases:**
     * - Callback page: Poll this endpoint after user returns from payment gateway
     * - Status page: Display real-time payment status to user
     * - Admin panel: Check customer payment issues
     */
    #[PathParameter('referenceNo', description: 'Unique top-up reference number returned by InitiateTopUp endpoint. Format: "TOPUP-{ID}" (e.g., "TOPUP-ABC123"). Case-sensitive.', type: 'string', example: 'TOPUP-ABC123')]
    public function __invoke(Request $request, string $referenceNo): array
    {
        try {
            $user = $request->user();
            $topUp = $user->getTopUpByReference($referenceNo);
            $topUpData = TopUpData::fromModel($topUp);

            return [
                'data' => $topUpData,
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'version' => 'v1',
                ],
            ];
        } catch (TopUpException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }
    }
}
