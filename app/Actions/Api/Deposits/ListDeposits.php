<?php

declare(strict_types=1);

namespace App\Actions\Api\Deposits;

use App\Data\DepositTransactionData;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\DataCollection;

/**
 * List incoming deposits via API.
 *
 * Endpoint: GET /api/v1/deposits
 */
class ListDeposits
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->integer('per_page', 20), 100);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $institution = $request->input('institution');

        // Get all senders with their pivot data
        $sendersQuery = $user->senders();

        // Filter by date range
        if ($dateFrom) {
            $sendersQuery->wherePivot('last_transaction_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $sendersQuery->wherePivot('last_transaction_at', '<=', $dateTo);
        }

        // Search by sender name or mobile
        if ($search) {
            $sendersQuery->where(function ($q) use ($search) {
                $q->where('mobile', 'like', "%{$search}%");
                // Note: name is an accessor, can't search directly
            });
        }

        $senders = $sendersQuery->get();

        // Collect all deposit transactions from all senders
        $deposits = collect();
        
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

                // Add amount to metadata for DTO
                $txMetadata['amount'] = $txMetadata['amount'] ?? 0;
                $txMetadata['currency'] = 'PHP';

                $deposits->push([
                    'sender' => $sender,
                    'metadata' => $txMetadata,
                    'timestamp' => $txMetadata['timestamp'] ?? null,
                ]);
            }
        }

        // Sort by timestamp desc
        $deposits = $deposits->sortByDesc('timestamp');

        // Manual pagination
        $total = $deposits->count();
        $currentPage = max(1, $request->integer('page', 1));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($currentPage, $lastPage);
        
        $offset = ($currentPage - 1) * $perPage;
        $paginatedDeposits = $deposits->slice($offset, $perPage)->values();

        // Transform to DTOs
        $depositData = $paginatedDeposits->map(function ($item) {
            return DepositTransactionData::fromMetadata($item['sender'], $item['metadata']);
        });

        return ApiResponse::success([
            'data' => new DataCollection(DepositTransactionData::class, $depositData),
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? min($offset + $perPage, $total) : null,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
                'institution' => $institution,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:50'],
        ];
    }
}
