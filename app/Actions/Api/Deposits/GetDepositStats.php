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

        // Calculate stats from all transactions
        $totalAmount = 0;
        $totalCount = 0;
        $todayCount = 0;
        $monthCount = 0;
        
        // 1. Include wallet top-up transactions
        $walletTransactions = $user->walletTransactions()
            ->where('type', 'deposit')
            ->where('amount', '>', 0);
        
        if ($dateFrom) {
            $walletTransactions->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $walletTransactions->where('created_at', '<=', $dateTo);
        }
        
        foreach ($walletTransactions->get() as $tx) {
            // Apply institution filter if provided
            $txInstitution = $tx->meta['gateway'] ?? null;
            if ($institution && $txInstitution !== $institution) continue;
            
            $amount = $tx->amount / 100; // Convert cents to pesos
            $totalAmount += $amount;
            $totalCount++;
            
            if ($tx->created_at->isToday()) {
                $todayCount++;
            }
            if ($tx->created_at->isCurrentMonth()) {
                $monthCount++;
            }
        }
        
        // 2. Get QR deposits from external senders
        $sendersQuery = $user->senders();

        // Apply date filters
        if ($dateFrom) {
            $sendersQuery->wherePivot('last_transaction_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $sendersQuery->wherePivot('last_transaction_at', '<=', $dateTo);
        }

        $senders = $sendersQuery->get();
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
