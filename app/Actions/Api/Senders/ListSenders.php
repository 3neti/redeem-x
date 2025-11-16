<?php

declare(strict_types=1);

namespace App\Actions\Api\Senders;

use App\Data\SenderData;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\DataCollection;

/**
 * List sender contacts via API.
 *
 * Endpoint: GET /api/v1/senders
 */
class ListSenders
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->integer('per_page', 20), 100);
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'last_transaction_at');
        $sortOrder = $request->input('sort_order', 'desc');

        // Get senders with pivot data
        $sendersQuery = $user->senders();

        // Search by mobile
        if ($search) {
            $sendersQuery->where('mobile', 'like', "%{$search}%");
        }

        // Sort
        $validSortColumns = ['last_transaction_at', 'total_sent', 'transaction_count'];
        if (in_array($sortBy, $validSortColumns)) {
            $sendersQuery->orderByPivot($sortBy, $sortOrder);
        }

        $senders = $sendersQuery->paginate($perPage);

        // Transform to SenderData DTOs
        $senderData = $senders->map(function ($sender) use ($user) {
            return SenderData::fromContactWithPivot($sender, $user);
        });

        return ApiResponse::success([
            'data' => new DataCollection(SenderData::class, $senderData),
            'pagination' => [
                'current_page' => $senders->currentPage(),
                'per_page' => $senders->perPage(),
                'total' => $senders->total(),
                'last_page' => $senders->lastPage(),
                'from' => $senders->firstItem(),
                'to' => $senders->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:last_transaction_at,total_sent,transaction_count'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
