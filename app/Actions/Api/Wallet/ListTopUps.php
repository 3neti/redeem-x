<?php

namespace App\Actions\Api\Wallet;

use App\Data\Api\Wallet\TopUpData;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class ListTopUps
{
    use AsAction;

    /**
     * Get all top-ups for the user.
     */
    public function handle(User $user, ?string $status = null): Collection
    {
        $query = $user->topUps()->latest();

        if ($status) {
            $query->where('payment_status', strtoupper($status));
        }

        return $query->get()->map(fn ($topUp) => TopUpData::fromModel($topUp));
    }

    /**
     * Handle as controller action.
     */
    public function asController(): array
    {
        $status = request()->query('status');

        if ($status && !in_array(strtoupper($status), ['PENDING', 'PAID', 'FAILED', 'EXPIRED'])) {
            throw ValidationException::withMessages([
                'status' => ['Invalid status. Must be one of: pending, paid, failed, expired'],
            ]);
        }

        $user = auth()->user();
        $topUps = $this->handle($user, $status);

        return [
            'data' => $topUps,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
                'count' => $topUps->count(),
            ],
        ];
    }
}
