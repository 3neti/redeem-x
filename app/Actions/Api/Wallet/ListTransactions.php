<?php

namespace App\Actions\Api\Wallet;

use App\Data\Api\Wallet\TransactionData;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;

/**
 * List Wallet Transactions
 *
 * Retrieve a complete history of all wallet transactions including deposits and withdrawals.
 * 
 * This endpoint shows all money movements in your wallet, useful for:
 * - Account statements and financial reports
 * - Reconciliation and auditing
 * - Displaying transaction history to users
 * - Debugging wallet balance discrepancies
 * 
 * **Transaction Types:**
 * - `deposit`: Money added to wallet (top-ups, refunds, credits)
 * - `withdraw`: Money deducted from wallet (voucher generation, fees, transfers)
 * 
 * Transactions are returned in reverse chronological order (newest first).
 *
 * @group Wallet
 * @authenticated
 */
#[Group('Wallet')]
class ListTransactions
{
    use AsAction;

    /**
     * Get wallet transactions for the user.
     */
    public function handle(User $user, ?string $type = null): Collection
    {
        $query = $user->wallet->transactions()->latest();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get()->map(fn ($transaction) => TransactionData::fromModel($transaction));
    }

    /**
     * List wallet transactions
     * 
     * Retrieve your complete wallet transaction history with optional filtering by transaction type.
     * 
     * **Response includes** (for each transaction):
     * - `id`: Unique transaction ID
     * - `type`: Transaction type (deposit or withdraw)
     * - `amount`: Transaction amount (positive for deposits, negative for withdrawals)
     * - `balance_after`: Wallet balance after this transaction
     * - `description`: Human-readable transaction description
     * - `meta`: Additional metadata (e.g., voucher code, top-up reference)
     * - `created_at`: Transaction timestamp
     * - `confirmed`: Whether transaction is confirmed (true for most transactions)
     * 
     * **Example Transactions:**
     * - Deposit: "Top-up via GCash - TOPUP-ABC123"
     * - Withdraw: "Voucher generation - CODE-XYZ789"
     * - Deposit: "Voucher cancellation refund - CODE-OLD123"
     * 
     * Results are paginated and sorted newest first. Use type filter to show only deposits or withdrawals.
     */
    #[QueryParameter('type', description: '*optional* - Filter by transaction type. "deposit" shows only money added to wallet (top-ups, refunds, credits). "withdraw" shows only money deducted (voucher generation, fees). Omit to view all transaction types. Case-sensitive.', type: 'string', example: 'deposit')]
    public function asController(): array
    {
        $type = request()->query('type');

        if ($type && !in_array($type, ['deposit', 'withdraw'])) {
            throw ValidationException::withMessages([
                'type' => ['Invalid type. Must be one of: deposit, withdraw'],
            ]);
        }

        $user = auth()->user();
        $transactions = $this->handle($user, $type);

        return [
            'data' => $transactions,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
                'count' => $transactions->count(),
            ],
        ];
    }
}
