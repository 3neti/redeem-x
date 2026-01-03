<?php

namespace App\Actions\Payment;

use App\Actions\Voucher\ValidateVoucherCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Process voucher payment - transfer from Cash wallet to User wallet.
 *
 * This is the "internal payment" path - money stays in the system.
 * Contrast with ProcessRedemption → DisburseCash which withdraws from Cash wallet to external bank.
 */
class PayWithVoucher
{
    use AsAction;

    /**
     * Pay with voucher - transfer from Cash wallet to User wallet.
     *
     * @param  User  $user  User receiving payment
     * @param  string  $code  Voucher code
     * @return array{success: bool, amount: float, new_balance: float, voucher_code: string}
     *
     * @throws \Illuminate\Validation\ValidationException  If validation fails
     * @throws \RuntimeException  If payable validation fails
     */
    public function handle(User $user, string $code): array
    {
        // Step 1: Validate voucher (reuses existing action)
        $validator = new ValidateVoucherCode();
        $voucher = $validator->validateOrFail($code);
        
        // Step 2: Validate payable restriction (if voucher requires specific vendor alias)
        $this->validatePayable($voucher, $user);

        Log::info('[PayWithVoucher] Voucher validated and payable check passed, transferring from Cash wallet', [
            'user_id' => $user->id,
            'voucher' => $voucher->code,
            'cash_id' => $voucher->cash->id,
            'amount' => $voucher->amount,
            'cash_balance_before' => $voucher->cash->balanceFloat,
        ]);

        // Step 2: Transfer from Cash wallet to User wallet (atomic transaction)
        return DB::transaction(function () use ($user, $voucher) {
            $cash = $voucher->cash;
            $amount = $voucher->instructions->cash->amount;
            
            // Get issuer for sender attribution
            $issuer = User::find($voucher->owner_id);

            // CRITICAL: Transfer FROM Cash wallet TO User wallet
            // This is the correct money flow - Cash entity holds escrowed funds
            $transfer = $cash->transfer($user, $amount * 100, [
                'type' => 'voucher_payment', // Legacy compatibility
                'deposit_type' => 'voucher_payment',
                'payment_method' => 'Voucher',
                'voucher_code' => $voucher->code,
                'voucher_uuid' => $voucher->uuid,
                'issuer_id' => $voucher->owner_id,
                'sender_id' => $voucher->owner_id,
                'sender_name' => $issuer?->name ?? 'Unknown',
                'sender_identifier' => $voucher->code,
            ]);

            // Mark voucher as redeemed (direct update, bypasses observer to avoid DisburseCash pipeline)
            $voucher->update([
                'redeemed_at' => now(),
                'metadata' => array_merge($voucher->metadata ?? [], [
                    'redemption_type' => 'voucher_payment',
                    'redeemer_user_id' => $user->id,
                    'transfer_uuid' => $transfer->uuid,
                ]),
            ]);

            Log::info('[PayWithVoucher] Payment completed', [
                'user_id' => $user->id,
                'voucher' => $voucher->code,
                'amount' => $amount,
                'cash_balance_after' => $cash->fresh()->balanceFloat,
                'user_balance_after' => $user->fresh()->balanceFloat,
                'transfer_uuid' => $transfer->uuid,
            ]);

            return [
                'success' => true,
                'amount' => $amount,
                'new_balance' => $user->fresh()->balanceFloat,
                'voucher_code' => $voucher->code,
            ]);
        });
    }
    
    /**
     * Validate that user has the required vendor alias (payable restriction).
     *
     * @param  \LBHurtado\Voucher\Models\Voucher  $voucher
     * @param  User  $user
     * @return void
     *
     * @throws \RuntimeException  If payable validation fails
     */
    protected function validatePayable($voucher, User $user): void
    {
        // Check if voucher has payable restriction
        $payableAliasId = $voucher->instructions->cash->validation->payable ?? null;
        
        if (!$payableAliasId) {
            // No restriction - any authenticated user can redeem
            return;
        }
        
        // User must have an active vendor alias
        $vendorAlias = $user->primaryVendorAlias;
        
        if (!$vendorAlias || !$vendorAlias->isActive()) {
            Log::warning('[PayWithVoucher] User has no active vendor alias', [
                'user_id' => $user->id,
                'voucher' => $voucher->code,
                'required_alias_id' => $payableAliasId,
            ]);
            
            throw new \RuntimeException(
                'This voucher requires a vendor alias. Please contact support to get your merchant credentials.'
            );
        }
        
        // Vendor alias must match the payable restriction
        if ($vendorAlias->id !== $payableAliasId) {
            Log::warning('[PayWithVoucher] Vendor alias mismatch', [
                'user_id' => $user->id,
                'voucher' => $voucher->code,
                'user_alias_id' => $vendorAlias->id,
                'user_alias' => $vendorAlias->alias,
                'required_alias_id' => $payableAliasId,
            ]);
            
            // Load the required alias for error message
            $requiredAlias = \LBHurtado\Merchant\Models\VendorAlias::find($payableAliasId);
            
            throw new \RuntimeException(
                sprintf(
                    'This voucher is payable to %s. You are logged in as %s.',
                    $requiredAlias->alias ?? 'another vendor',
                    $vendorAlias->alias
                )
            );
        }
        
        Log::info('[PayWithVoucher] Payable validation passed', [
            'user_id' => $user->id,
            'voucher' => $voucher->code,
            'vendor_alias' => $vendorAlias->alias,
        ]);
    }
}
