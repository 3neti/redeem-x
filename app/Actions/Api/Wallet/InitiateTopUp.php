<?php

namespace App\Actions\Api\Wallet;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use LBHurtado\PaymentGateway\Data\TopUp\TopUpResultData;
use LBHurtado\PaymentGateway\Exceptions\TopUpException;
use Lorisleiva\Actions\Concerns\AsAction;

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
     * Handle as controller action.
     */
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
