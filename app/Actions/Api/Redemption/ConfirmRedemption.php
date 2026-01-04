<?php

declare(strict_types=1);

namespace App\Actions\Api\Redemption;

use App\Actions\Voucher\ProcessRedemption;
use App\Http\Responses\ApiResponse;
use App\Services\VoucherRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Exceptions\InvalidSettlementRailException;
use LBHurtado\Voucher\Exceptions\RedemptionException;
use Propaganistas\LaravelPhone\PhoneNumber;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmRedemption
{
    use AsAction;

    public function asController(): JsonResponse
    {
        try {
            // Get request data
            $voucherCode = request()->input('voucher_code');
            $mobile = request()->input('mobile');
            $country = request()->input('country', 'PH');
            $bankCode = request()->input('bank_code');
            $accountNumber = request()->input('account_number');
            $inputs = request()->input('inputs', []);

            // Validate required fields
            if (!$voucherCode || !$mobile) {
                Log::warning('[ConfirmRedemption] Missing required fields', [
                    'voucher' => $voucherCode,
                    'mobile' => $mobile,
                ]);
                return ApiResponse::error('Voucher code and mobile number are required', 400);
            }

            // Find voucher
            $voucher = Voucher::where('code', $voucherCode)->first();

            if (!$voucher) {
                Log::warning('[ConfirmRedemption] Voucher not found', [
                    'voucher' => $voucherCode,
                ]);
                return ApiResponse::error('Voucher not found', 404);
            }

            // Check if voucher is already redeemed
            if ($voucher->isRedeemed()) {
                Log::warning('[ConfirmRedemption] Voucher already redeemed', [
                    'voucher' => $voucherCode,
                ]);
                return ApiResponse::error('This voucher has already been redeemed', 422);
            }

            // Check if voucher is expired
            if ($voucher->isExpired()) {
                Log::warning('[ConfirmRedemption] Voucher expired', [
                    'voucher' => $voucherCode,
                ]);
                return ApiResponse::error('This voucher has expired', 422);
            }

            // Prepare bank account data
            $bankAccount = [
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
            ];

            Log::info('[ConfirmRedemption] Confirming redemption', [
                'voucher' => $voucherCode,
                'mobile' => $mobile,
            ]);

            try {
                // Create PhoneNumber instance
                $phoneNumber = new PhoneNumber($mobile, $country);
                
                // Validate using Unified Validation Gateway
                $service = new VoucherRedemptionService();
                $context = $service->resolveContextFromArray([
                    'mobile' => $mobile,
                    'secret' => request()->input('secret'),
                    'inputs' => $inputs,
                    'bank_account' => $bankAccount,
                ]);
                
                $service->validateRedemption($voucher, $context);

                // Process redemption (uses transaction)
                ProcessRedemption::run(
                    $voucher,
                    $phoneNumber,
                    $inputs,
                    $bankAccount
                );

                Log::info('[ConfirmRedemption] Redemption successful', [
                    'voucher' => $voucherCode,
                    'mobile' => $phoneNumber->formatE164(),
                ]);

                // Return success with voucher data and rider info
                return response()->json([
                    'success' => true,
                    'data' => [
                        'voucher' => [
                            'code' => $voucher->code,
                            'amount' => $voucher->amount,
                            'currency' => $voucher->currency,
                        ],
                        'mobile' => $mobile,
                        'message' => 'Voucher redeemed successfully!',
                        'rider' => [
                            'message' => $voucher->instructions->rider->message ?? null,
                            'url' => $voucher->instructions->rider->url ?? null,
                        ],
                    ],
                ], 200);
            } catch (RedemptionException $e) {
                // Validation failed (secret, mobile, payable, inputs, kyc, location, time)
                Log::warning('[ConfirmRedemption] Validation failed', [
                    'voucher' => $voucherCode,
                    'error' => $e->getMessage(),
                ]);

                return ApiResponse::error($e->getMessage(), 422);
            } catch (\Throwable $e) {
                // Handle known business exceptions gracefully (no stack trace)
                if ($e instanceof InvalidSettlementRailException) {
                    Log::warning('[ConfirmRedemption] Settlement rail validation failed', [
                        'voucher' => $voucherCode,
                        'message' => $e->getMessage(),
                    ]);
                } else {
                    // Log unexpected errors with full trace for debugging
                    Log::error('[ConfirmRedemption] Redemption processing failed', [
                        'voucher' => $voucherCode,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                return ApiResponse::error('Failed to process redemption: ' . $e->getMessage(), 422);
            }
        } catch (\Throwable $e) {
            Log::error('[ConfirmRedemption] Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to confirm redemption', 500);
        }
    }
}
