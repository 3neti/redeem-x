<?php

namespace App\SMS\Handlers;

use App\Actions\Api\Redemption\RedeemViaSms;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LBHurtado\OmniChannel\Handlers\BaseSMSHandler;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;

/**
 * Handle SMS redemption for bare voucher codes.
 * 
 * This handler processes messages that look like voucher codes
 * and attempts to redeem them automatically if they meet criteria:
 * - Type: REDEEMABLE only (Phase 1)
 * - No user interaction required (no inputs/validations)
 * - Valid status (not expired, not already redeemed)
 * 
 * Example: User sends "ABC123" → System redeems and disburses
 */
class SMSRedeem extends BaseSMSHandler
{
    /**
     * This handler does not require authentication.
     * 
     * Anyone can attempt to redeem a voucher by sending its code.
     * The voucher itself may have validations, but the handler is public.
     *
     * @return bool
     */
    protected function requiresAuth(): bool
    {
        return false;
    }
    
    /**
     * Handle the voucher redemption request.
     *
     * @param User|null $user The authenticated user (will be null for this public handler)
     * @param array $values Parsed values from the SMS message
     * @param string $from Sender's phone number
     * @param string $to Receiver's phone number
     * @return JsonResponse The response to send back
     */
    protected function handle(?User $user, array $values, string $from, string $to): JsonResponse
    {
        $message = trim($values['message']);
        
        // 1. Pattern validation - is this a voucher code?
        if (!$this->looksLikeVoucherCode($message)) {
            $this->logInfo('Not a voucher code pattern, ignoring', ['message' => $message]);
            return response()->json(['message' => null]);
        }
        
        // 2. Find voucher in database
        $voucher = $this->findVoucher($message);
        if (!$voucher) {
            $this->logInfo('Voucher not found, ignoring', ['code' => strtoupper($message)]);
            return response()->json(['message' => null]);
        }
        
        $this->logInfo('Voucher found', [
            'code' => $voucher->code,
            'type' => $voucher->voucher_type->value,
            'redeemed' => $voucher->isRedeemed(),
            'expired' => $voucher->isExpired(),
        ]);
        
        // 3. Type validation - only REDEEMABLE in Phase 1
        if (!$this->isSupportedVoucherType($voucher)) {
            $this->logWarning('Non-REDEEMABLE voucher type', ['type' => $voucher->voucher_type->value]);
            return $this->unsupportedTypeResponse();
        }
        
        // 4. Interaction requirements check - must be simple redemption
        if ($this->requiresUserInteraction($voucher)) {
            $this->logWarning('Voucher requires user interaction', [
                'has_inputs' => !empty($voucher->instructions->inputs->fields ?? []),
                'has_validation' => $voucher->instructions->cash->validation->secret 
                                 || $voucher->instructions->cash->validation->location,
            ]);
            return $this->requiresWebResponse($voucher->code);
        }
        
        // 5. Status validation - must be unredeemed and not expired
        if ($statusError = $this->validateVoucherStatus($voucher)) {
            return $statusError;
        }
        
        // 6. Execute redemption
        return $this->executeRedemption($voucher, $from);
    }
    
    /**
     * Check if message looks like a voucher code.
     * 
     * Voucher codes are 4-20 characters, alphanumeric + hyphens.
     *
     * @param string $message The SMS message
     * @return bool
     */
    private function looksLikeVoucherCode(string $message): bool
    {
        return preg_match('/^[A-Z0-9-]{4,20}$/i', $message) === 1;
    }
    
    /**
     * Find voucher by code in database.
     *
     * @param string $code Voucher code (case-insensitive)
     * @return Voucher|null
     */
    private function findVoucher(string $code): ?Voucher
    {
        $voucherCode = strtoupper($code);
        return Voucher::where('code', $voucherCode)->first();
    }
    
    /**
     * Check if voucher type is supported for SMS redemption.
     * 
     * Phase 1: Only REDEEMABLE vouchers
     * Phase 2+: Add PAYABLE and SETTLEMENT
     *
     * @param Voucher $voucher
     * @return bool
     */
    private function isSupportedVoucherType(Voucher $voucher): bool
    {
        return $voucher->voucher_type === VoucherType::REDEEMABLE;
    }
    
    /**
     * Check if voucher requires user interaction.
     * 
     * Returns true if:
     * - Voucher has input fields to collect
     * - Voucher has secret validation
     * - Voucher has location validation
     *
     * @param Voucher $voucher
     * @return bool
     */
    private function requiresUserInteraction(Voucher $voucher): bool
    {
        $hasInputs = !empty($voucher->instructions->inputs->fields ?? []);
        $hasValidation = $voucher->instructions->cash->validation->secret 
                      || $voucher->instructions->cash->validation->location;
        
        return $hasInputs || $hasValidation;
    }
    
    /**
     * Validate voucher status.
     * 
     * Checks:
     * - Not already redeemed
     * - Not expired
     * 
     * Returns error response if invalid, null if valid.
     *
     * @param Voucher $voucher
     * @return JsonResponse|null
     */
    private function validateVoucherStatus(Voucher $voucher): ?JsonResponse
    {
        if ($voucher->isRedeemed()) {
            return response()->json([
                'message' => "❌ This voucher has already been redeemed."
            ]);
        }
        
        if ($voucher->isExpired()) {
            return response()->json([
                'message' => "❌ This voucher has expired."
            ]);
        }
        
        return null;
    }
    
    /**
     * Execute voucher redemption via API.
     * 
     * Calls RedeemViaSms action to process the redemption
     * and disbursement.
     *
     * @param Voucher $voucher
     * @param string $from Sender's mobile number
     * @return JsonResponse
     */
    private function executeRedemption(Voucher $voucher, string $from): JsonResponse
    {
        $this->logInfo('Attempting redemption', ['code' => $voucher->code, 'from' => $from]);
        
        try {
            // Create request for RedeemViaSms action
            $actionRequest = ActionRequest::create('', 'POST', [
                'voucher_code' => $voucher->code,
                'mobile' => $from,
                'bank_spec' => null, // Use default/GCash
            ]);
            
            $result = (new RedeemViaSms())->asController($actionRequest);
            
            if ($result->status() === 200) {
                $data = json_decode($result->getContent(), true);
                return response()->json([
                    'message' => $data['message'] ?? "✅ Voucher redeemed successfully!"
                ]);
            } else {
                $data = json_decode($result->getContent(), true);
                return response()->json([
                    'message' => $data['message'] ?? "⚠️ Failed to redeem voucher. Please try again."
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('Redemption failed', [
                'code' => $voucher->code,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => "⚠️ Failed to redeem voucher. Please try again or contact support."
            ]);
        }
    }
    
    /**
     * Response for unsupported voucher types.
     *
     * @return JsonResponse
     */
    private function unsupportedTypeResponse(): JsonResponse
    {
        return response()->json([
            'message' => "⚠️ SMS redemption for this voucher type is under construction. Please use the web to redeem."
        ]);
    }
    
    /**
     * Response for vouchers requiring web interaction.
     *
     * @param string $code Voucher code
     * @return JsonResponse
     */
    private function requiresWebResponse(string $code): JsonResponse
    {
        $url = config('app.url') . "/disburse?code={$code}";
        return response()->json([
            'message' => "⚠️ This voucher requires additional information. Please redeem via web: {$url}"
        ]);
    }
}
