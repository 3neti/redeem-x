<?php

declare(strict_types=1);

namespace App\Actions\Api\Deposits;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Get deposit statistics via API.
 *
 * Endpoint: GET /api/v1/deposits/stats
 */
class GetDepositStats
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $institution = $request->input('institution');

        // Get all senders with their pivot data
        $sendersQuery = $user->senders();

        // Apply date filters
        if ($dateFrom) {
            $sendersQuery->wherePivot('last_transaction_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $sendersQuery->wherePivot('last_transaction_at', '<=', $dateTo);
        }

        $senders = $sendersQuery->get();

        // Calculate stats from all transactions
        $totalAmount = 0;
        $totalCount = 0;
        $todayCount = 0;
        $monthCount = 0;
        $uniqueSenders = $senders->count();

        foreach ($senders as $sender) {
            $pivot = $sender->pivot;
            $metadata = is_string($pivot->metadata) 
                ? json_decode($pivot->metadata, true) 
                : ($pivot->metadata ?? []);

            if (!is_array($metadata)) {
                continue;
            }

            foreach ($metadata as $txMetadata) {
                // Filter by institution if provided
                if ($institution && ($txMetadata['institution'] ?? null) !== $institution) {
                    continue;
                }

                $amount = ($txMetadata['amount'] ?? 0);
                $totalAmount += $amount;
                $totalCount++;

                // Count today's deposits
                $timestamp = $txMetadata['timestamp'] ?? null;
                if ($timestamp) {
                    $txDate = \Carbon\Carbon::parse($timestamp);
                    if ($txDate->isToday()) {
                        $todayCount++;
                    }
                    if ($txDate->isCurrentMonth()) {
                        $monthCount++;
                    }
                }
            }
        }

        return ApiResponse::success([
            'stats' => [
                'total' => $totalCount,
                'total_amount' => $totalAmount,
                'today' => $todayCount,
                'this_month' => $monthCount,
                'unique_senders' => $uniqueSenders,
                'currency' => 'PHP',
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'institution' => ['nullable', 'string', 'max:50'],
        ];
    }
}
