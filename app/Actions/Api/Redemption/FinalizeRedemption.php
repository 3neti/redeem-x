<?php

declare(strict_types=1);

namespace App\Actions\Api\Redemption;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

class FinalizeRedemption
{
    use AsAction;

    public function asController(): JsonResponse
    {
        try {
            // Get voucher code from request (query param or body)
            $voucherCode = request()->input('voucher_code') ?? request()->query('voucher_code');

            if (!$voucherCode) {
                Log::warning('[FinalizeRedemption] Missing voucher code');
                return ApiResponse::error('Voucher code is required', 400);
            }

            // Find voucher
            $voucher = Voucher::where('code', $voucherCode)->first();

            if (!$voucher) {
                Log::warning('[FinalizeRedemption] Voucher not found', [
                    'voucher' => $voucherCode,
                ]);
                return ApiResponse::error('Voucher not found', 404);
            }

            // Check if voucher is already redeemed
            if ($voucher->isRedeemed()) {
                Log::warning('[FinalizeRedemption] Voucher already redeemed', [
                    'voucher' => $voucherCode,
                ]);
                return ApiResponse::error('This voucher has already been redeemed', 422);
            }

            // Check if voucher is expired
            if ($voucher->isExpired()) {
                Log::warning('[FinalizeRedemption] Voucher expired', [
                    'voucher' => $voucherCode,
                ]);
                return ApiResponse::error('This voucher has expired', 422);
            }

            Log::info('[FinalizeRedemption] Voucher validated for finalization', [
                'voucher' => $voucherCode,
            ]);

            // For API flow, just return basic voucher info
            // The frontend will merge this with sessionStorage data
            return response()->json([
                'success' => true,
                'data' => [
                    'voucher' => [
                        'code' => $voucher->code,
                        'amount' => $voucher->amount,
                        'currency' => $voucher->currency,
                    ],
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[FinalizeRedemption] Error retrieving finalization data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve finalization data', 500);
        }
    }

    /**
     * Format bank account for display.
     *
     * @param  string|null  $bankCode
     * @param  string|null  $accountNumber
     * @return string|null
     */
    private function formatBankAccount(?string $bankCode, ?string $accountNumber): ?string
    {
        if (empty($bankCode) || empty($accountNumber)) {
            return null;
        }

        $banks = $this->getBanksList();
        $bank = collect($banks)->firstWhere('code', $bankCode);
        $bankName = $bank['name'] ?? $bankCode;

        return "{$bankName} ({$accountNumber})";
    }

    /**
     * Get banks list.
     *
     * @return array
     */
    private function getBanksList(): array
    {
        return \LBHurtado\MoneyIssuer\Support\BankRegistry::all();
    }
}
