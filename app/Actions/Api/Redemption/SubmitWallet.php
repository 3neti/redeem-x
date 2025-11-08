<?php

declare(strict_types=1);

namespace App\Actions\Api\Redemption;

use App\Actions\Voucher\ProcessRedemption;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Submit wallet/mobile and redeem voucher.
 *
 * Endpoint: POST /api/v1/redemption/redeem
 */
class SubmitWallet
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $code = strtoupper(trim($request->input('code')));

        // Find voucher
        $voucher = Voucher::where('code', $code)->first();

        if (!$voucher) {
            return ApiResponse::error('Invalid voucher code.', 404);
        }

        // Check if already redeemed
        if ($voucher->redeemed_at) {
            return ApiResponse::error('This voucher has already been redeemed.', 400);
        }

        // Check if expired
        if ($voucher->expires_at && $voucher->expires_at->isPast()) {
            return ApiResponse::error('This voucher has expired.', 400);
        }

        // Check if not yet active
        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            return ApiResponse::error('This voucher is not yet active.', 400);
        }

        // Validate the request
        $validated = $request->validated();

        // Create PhoneNumber
        $phoneNumber = new PhoneNumber(
            $validated['mobile'],
            $validated['country'] ?? 'PH'
        );

        // Prepare bank account
        $bankAccount = [];
        if (!empty($validated['bank_code'])) {
            $bankAccount = [
                'bank_code' => $validated['bank_code'],
                'account_number' => $validated['account_number'] ?? $validated['mobile'],
            ];
        }

        // Prepare inputs
        $inputs = $validated['inputs'] ?? [];
        
        // Validate secret if required
        if (!empty($voucher->instructions->cash->validation->secret)) {
            $requiredSecret = $voucher->instructions->cash->validation->secret;
            $providedSecret = $validated['secret'] ?? null;
            
            if ($providedSecret !== $requiredSecret) {
                return ApiResponse::error('Invalid secret code.', 400);
            }
        }

        try {
            // Process redemption
            ProcessRedemption::run($voucher, $phoneNumber, $inputs, $bankAccount);

            // Reload voucher
            $voucher->refresh();

            return ApiResponse::success([
                'message' => 'Voucher redeemed successfully!',
                'voucher' => [
                    'code' => $voucher->code,
                    'amount' => $voucher->instructions->cash->amount ?? 0,
                    'currency' => $voucher->instructions->cash->currency ?? 'PHP',
                    'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                ],
                'rider' => [
                    'message' => $voucher->instructions->rider->message ?? null,
                    'url' => $voucher->instructions->rider->url ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('[SubmitWallet] Redemption failed', [
                'voucher' => $voucher->code,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to redeem voucher. Please try again.', 500);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:4'],
            'mobile' => ['required', (new Phone)->country('PH')->type('mobile')],
            'country' => ['nullable', 'string', 'size:2'],
            'bank_code' => ['nullable', 'string', 'max:50'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'secret' => ['nullable', 'string'],
            'inputs' => ['nullable', 'array'],
        ];
    }
}
