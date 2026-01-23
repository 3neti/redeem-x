<?php

namespace LBHurtado\Wallet\Actions;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * WithdrawCash
 * 
 * Withdraws the entire balance from a cash wallet after successful disbursement.
 * This represents money leaving the system entirely (sent to external bank account).
 * 
 * Usage:
 *   $transaction = WithdrawCash::run($cash, $operationId, $notes);
 * 
 * The cash wallet balance will be reduced to zero, recording that the
 * escrowed funds have been successfully disbursed to the redeemer.
 */
class WithdrawCash
{
    use AsAction;

    /**
     * Withdraw all funds from a cash wallet.
     * 
     * This should be called after successful disbursement to mark
     * the cash as fulfilled and remove it from the system's balance.
     * 
     * @param Wallet $cash The cash wallet to withdraw from
     * @param string|null $operationId Gateway operation/transaction ID
     * @param string|null $notes Optional notes for the transaction
     * @param array $additionalMeta Additional metadata to merge with defaults
     * @return Transaction The withdrawal transaction record
     */
    public function handle(
        Wallet $cash,
        ?string $operationId = null,
        ?string $notes = null,
        array $additionalMeta = []
    ): Transaction {
        $balance = $cash->wallet->balance; // In centavos
        
        if ($balance <= 0) {
            Log::warning('[WithdrawCash] Attempted withdrawal from zero-balance cash', [
                'cash_id' => $cash->getKey(),
                'balance' => $balance,
            ]);
            
            throw new \InvalidArgumentException(
                "Cash wallet #{$cash->getKey()} has zero balance"
            );
        }
        
        // Build base metadata
        $baseMeta = array_filter([
            'type' => 'disbursement',
            'operation_id' => $operationId,
            'notes' => $notes,
            'withdrawn_at' => now()->toIso8601String(),
        ]);
        
        // Merge with additional meta (additional meta takes precedence)
        $meta = array_merge($baseMeta, $additionalMeta);
        
        // Withdraw entire balance (money leaves system)
        $transaction = $cash->withdraw(
            $balance,
            $meta,
            true // confirmed
        );
        
        Log::info('[WithdrawCash] Cash withdrawn after disbursement', [
            'cash_id' => $cash->getKey(),
            'amount_centavos' => $balance,
            'amount_php' => $balance / 100,
            'transaction_uuid' => $transaction->uuid,
            'operation_id' => $operationId,
        ]);
        
        return $transaction;
    }
}
