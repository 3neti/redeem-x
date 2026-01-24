<?php

declare(strict_types=1);

namespace App\Actions\Api\Redemption;

use App\Events\VoucherRedeemedViaMessaging;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SMS Redemption Endpoint
 * 
 * Handles voucher redemption via SMS with bank account resolution.
 * Only works with simple vouchers (no validation/input requirements).
 * 
 * Endpoint: POST /api/v1/redeem/sms
 */
class RedeemViaSms
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        try {
            $voucherCode = strtoupper(trim($request->input('voucher_code')));
            $mobile = $request->input('mobile');
            $bankSpec = $request->input('bank_spec'); // null, "MAYA", "GCASH:09181111111"

            // Format mobile to E.164
            $mobile = $this->formatMobile($mobile);

            Log::info('[RedeemViaSms] Processing SMS redemption', [
                'voucher' => $voucherCode,
                'mobile' => $mobile,
                'bank_spec' => $bankSpec,
            ]);

            // Find voucher
            $voucher = Voucher::where('code', $voucherCode)->first();

            if (!$voucher) {
                return ApiResponse::error('Invalid voucher code.', 404);
            }

            // Check redemption status
            if ($voucher->isRedeemed()) {
                return ApiResponse::error('This voucher has already been redeemed.', 422);
            }

            if ($voucher->isExpired()) {
                return ApiResponse::error('This voucher has expired.', 422);
            }

            // Check if voucher is simple (no requirements)
            $hasRequirements = $this->checkVoucherRequirements($voucher);
            
            if ($hasRequirements) {
                $redemptionUrl = config('app.url') . '/redeem?code=' . $voucherCode;
                
                return response()->json([
                    'success' => false,
                    'error' => 'requires_web',
                    'message' => 'This voucher requires additional information. Please redeem via web.',
                    'redemption_url' => $redemptionUrl,
                ], 422);
            }

            // Find or create contact
            $contact = Contact::firstOrCreate(
                ['mobile' => $mobile],
                ['bank_account' => config('contact.default.bank_code', 'GCASH') . ':' . $this->toNationalFormat($mobile)]
            );

            // Resolve bank account
            $bankAccount = $this->resolveBankAccount($mobile, $bankSpec, $contact);

            // Parse bank code and account number
            try {
                $ba = BankAccount::fromBankAccount($bankAccount);
                $bankCode = $ba->getBankCode();
                $accountNumber = $ba->getAccountNumber();
            } catch (\Exception $e) {
                Log::error('[RedeemViaSms] Invalid bank account format', [
                    'bank_account' => $bankAccount,
                    'error' => $e->getMessage(),
                ]);
                return ApiResponse::error('Invalid bank account format.', 400);
            }

            // Update contact's bank account if different
            if ($contact->bank_account !== $bankAccount) {
                $contact->update(['bank_account' => $bankAccount]);
            }

            // Perform redemption in transaction
            DB::beginTransaction();
            
            try {
                // Override request data for ConfirmRedemption
                request()->merge([
                    'voucher_code' => $voucherCode,
                    'mobile' => $mobile,
                    'country' => 'PH',
                    'bank_code' => $bankCode,
                    'account_number' => $accountNumber,
                    'inputs' => [],
                ]);

                $result = (new ConfirmRedemption)->asController();
                
                if ($result->status() !== 200) {
                    DB::rollBack();
                    return $result;
                }

                DB::commit();

                // Refresh voucher to get updated state after redemption
                $voucher->refresh();

                // Fire event for messaging redemption
                event(new VoucherRedeemedViaMessaging(
                    voucher: $voucher,
                    contact: $contact,
                    channel: 'sms',
                    bankAccount: $bankAccount,
                    messageMetadata: [
                        'bank_spec' => $bankSpec,
                        'resolved_bank' => $bankAccount,
                    ]
                ));

                // Get voucher data from ConfirmRedemption result
                $resultData = json_decode($result->getContent(), true);
                $voucherData = $resultData['data']['voucher'] ?? [];
                $amount = $voucherData['amount'] ?? 0;

                Log::info('[RedeemViaSms] SMS redemption successful', [
                    'voucher' => $voucherCode,
                    'mobile' => $mobile,
                    'bank_account' => $bankAccount,
                ]);

                // Format success message
                $formattedAmount = number_format($amount, 2);
                $message = "Voucher {$voucherCode} redeemed (₱{$formattedAmount}). Funds sent to {$bankAccount}.";

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'voucher' => $voucherData,
                        'bank_account' => $bankAccount,
                        'mobile' => $mobile,
                    ],
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                
                Log::error('[RedeemViaSms] Redemption failed', [
                    'voucher' => $voucherCode,
                    'error' => $e->getMessage(),
                ]);

                return ApiResponse::error('Failed to redeem voucher. Please try again or contact support.', 500);
            }

        } catch (\Exception $e) {
            Log::error('[RedeemViaSms] Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('System error. Please try again later.', 500);
        }
    }

    public function rules(): array
    {
        return [
            'voucher_code' => ['required', 'string', 'min:4'],
            'mobile' => ['required', 'string'],
            'bank_spec' => ['nullable', 'string'],
        ];
    }

    /**
     * Check if voucher has validation or input requirements
     */
    private function checkVoucherRequirements(Voucher $voucher): bool
    {
        try {
            $instructions = $voucher->instructions;
            
            // Check validation requirements (except mobile - always allowed)
            if ($instructions->cash->validation) {
                $validation = $instructions->cash->validation;
                
                if ($validation->secret || $validation->location) {
                    return true;
                }
            }
            
            // Check input fields
            if ($instructions->inputs && $instructions->inputs->fields) {
                return count($instructions->inputs->fields) > 0;
            }
            
            return false;
        } catch (\Exception $e) {
            // If we can't read instructions, assume simple voucher
            return false;
        }
    }

    /**
     * Resolve bank account from bank_spec and contact
     */
    private function resolveBankAccount(string $mobile, ?string $bankSpec, Contact $contact): string
    {
        // Convert mobile to national format (09XX) for account number
        $accountNumber = $this->toNationalFormat($mobile);
        
        if (empty($bankSpec)) {
            // Use contact's default or GCash fallback
            return $contact->bank_account ?: ('GCASH:' . $accountNumber);
        }

        // Resolve friendly bank code to SWIFT code
        $bankCode = strtoupper($bankSpec);
        if (!str_contains($bankCode, ':')) {
            // Check if it's a friendly code (e.g., "GCASH" -> "GXCHPHM2XXX")
            $swiftCode = config("bank-aliases.{$bankCode}", $bankCode);
            return "{$swiftCode}:{$accountNumber}";
        }

        // Full spec provided (e.g., "GCASH:09181111111")
        // Resolve bank code part if it's friendly
        [$code, $account] = explode(':', $bankCode, 2);
        $swiftCode = config("bank-aliases.{$code}", $code);
        
        // Keep account as provided (user specified exact format)
        return "{$swiftCode}:{$account}";
    }

    /**
     * Format mobile number to E.164 (639XXXXXXXXX)
     */
    private function formatMobile(string $mobile): string
    {
        // Remove non-numeric characters
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        
        // Add country code if missing
        if (str_starts_with($mobile, '09')) {
            $mobile = '63' . substr($mobile, 1);
        } elseif (!str_starts_with($mobile, '63')) {
            $mobile = '63' . $mobile;
        }
        
        return $mobile;
    }

    /**
     * Convert E.164 mobile to national format (09XXXXXXXXX)
     */
    private function toNationalFormat(string $mobile): string
    {
        // Remove non-numeric characters
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        
        // Convert 639XXXXXXXXX to 09XXXXXXXXX
        if (str_starts_with($mobile, '63')) {
            return '0' . substr($mobile, 2);
        }
        
        // Already in national format or other format
        if (str_starts_with($mobile, '09')) {
            return $mobile;
        }
        
        // Assume it's missing leading 0
        return '0' . $mobile;
    }
}
