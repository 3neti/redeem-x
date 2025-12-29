<?php

namespace App\Actions\Api\Wallet;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use LBHurtado\PaymentGateway\Data\TopUp\TopUpResultData;
use LBHurtado\PaymentGateway\Exceptions\TopUpException;
use Lorisleiva\Actions\Concerns\AsAction;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;

/**
 * Initiate Wallet Top-Up
 *
 * Start a payment transaction to add funds to your wallet via supported payment gateways.
 * 
 * **Supported Payment Methods:**
 * - E-Wallets: GCash, Maya (formerly PayMaya)
 * - Banks: BDO, BPI, UnionBank, and other InstaPay-enabled banks
 * - Over-the-counter: 7-Eleven, Cebuana, etc. (via partner banks)
 * 
 * **Payment Flow:**
 * 1. Call this endpoint with desired amount
 * 2. Receive a payment URL in the response
 * 3. Redirect user to the payment URL
 * 4. User completes payment in their chosen method
 * 5. System automatically credits wallet upon successful payment
 * 6. Use `GetTopUpStatus` endpoint to check payment status
 * 
 * **Idempotency:** Supports idempotency keys to prevent duplicate charges on retry.
 *
 * @group Wallet
 * @authenticated
 */
#[Group('Wallet')]
class InitiateTopUp
{
    use AsAction;

    /**
     * Initiate a top-up for the user.
     *
     * @throws TopUpException
     */
    public function handle(
        User $user,
        float $amount,
        string $gateway = 'netbank',
        ?string $institutionCode = null
    ): TopUpResultData {
        return $user->initiateTopUp($amount, $gateway, $institutionCode);
    }

    /**
     * Initiate wallet top-up
     * 
     * Begin a top-up transaction to add funds to your wallet. Returns a payment URL where the user completes payment.
     * 
     * **Response includes:**
     * - `payment_url`: URL to redirect user for payment (GCash, Maya, bank, etc.)
     * - `reference_no`: Unique transaction reference (e.g., "TOPUP-ABC123")
     * - `amount`: Requested top-up amount
     * - `status`: Initial status (usually "PENDING")
     * - `expires_at`: Payment link expiration time
     * 
     * **Important:**
     * - Save the `reference_no` to check payment status later
     * - Redirect user to `payment_url` immediately
     * - Payment expires after 30 minutes (configurable)
     * - Use webhooks or polling to detect successful payment
     */
    #[BodyParameter('amount', description: '**REQUIRED**. Top-up amount in major units (whole PHP). Range: ₱1 - ₱50,000 per transaction. This amount will be credited to your wallet after successful payment. Example: 1000 = ₱1,000.00', type: 'number', example: 1000)]
    #[BodyParameter('gateway', description: '*optional* - Payment gateway provider. Currently only "netbank" (NetBank Direct Checkout) is supported. This gateway supports GCash, Maya, InstaPay banks, and OTC channels. Default: "netbank"', type: 'string', example: 'netbank')]
    #[BodyParameter('institution_code', description: '*optional* - Pre-select payment method for user convenience. Valid codes: "GCASH", "MAYA", "BDO", "BPI", "UBP" (UnionBank), etc. If specified, user will be directed to this payment option first. Useful for deep-linking.', type: 'string', example: 'GCASH')]
    public function asController(): array
    {
        $validated = request()->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
            'gateway' => ['sometimes', 'string', 'in:netbank'],
            'institution_code' => ['nullable', 'string'],
        ]);

        try {
            $user = auth()->user();
            $result = $this->handle(
                $user,
                $validated['amount'],
                $validated['gateway'] ?? 'netbank',
                $validated['institution_code'] ?? null
            );
            
            // Store idempotency key in the top-up record
            $idempotencyKey = request()->header('Idempotency-Key');
            if ($idempotencyKey && $result->reference_no) {
                $topUp = $user->topUps()->where('reference_no', $result->reference_no)->first();
                if ($topUp) {
                    $topUp->update([
                        'idempotency_key' => $idempotencyKey,
                        'idempotency_created_at' => now(),
                    ]);
                }
            }

            return [
                'data' => $result,
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'version' => 'v1',
                ],
            ];
        } catch (TopUpException $e) {
            throw ValidationException::withMessages([
                'amount' => [$e->getMessage()],
            ]);
        }
    }
}
