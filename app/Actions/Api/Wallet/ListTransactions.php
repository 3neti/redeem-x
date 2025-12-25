<?php

namespace App\Actions\Api\Wallet;

use App\Data\Api\Wallet\TransactionData;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

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
     * Handle as controller action.
     */
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
