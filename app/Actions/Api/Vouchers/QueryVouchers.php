<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\DataCollection;
use Dedoc\Scramble\Attributes\Group;

/**
 * @group Vouchers
 *
 * Query vouchers with filters via API.
 *
 * Endpoint: GET /api/v1/vouchers/query
 */
#[Group('Vouchers')]
class QueryVouchers
{
    use AsAction;

    /**
     * Query vouchers with advanced filters
     * 
     * Search vouchers using external metadata, status, and validation status filters.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        $query = Voucher::query()
            ->where('owner_id', $request->user()->id);

        // Filter by external_type
        if ($request->filled('external_type')) {
            $query->whereExternal('external_type', $request->input('external_type'));
        }

        // Filter by external_id
        if ($request->filled('external_id')) {
            $query->whereExternal('external_id', $request->input('external_id'));
        }

        // Filter by reference_id
        if ($request->filled('reference_id')) {
            $query->whereExternal('reference_id', $request->input('reference_id'));
        }

        // Filter by user_id
        if ($request->filled('user_id')) {
            $query->whereExternal('user_id', $request->input('user_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->input('status');
            match ($status) {
                'active' => $query->whereNull('redeemed_at'),
                'redeemed' => $query->whereNotNull('redeemed_at'),
                'expired' => $query->where('expires_at', '<', now()),
                default => null,
            };
        }

        // Filter by validation status
        if ($request->filled('validation_status')) {
            match ($request->input('validation_status')) {
                'passed' => $query->whereValidationPassed(),
                'failed' => $query->whereValidationFailed(),
                'blocked' => $query->whereValidationBlocked(),
                default => null,
            };
        }

        // Order by
        $orderBy = $request->input('order_by', 'created_at');
        $orderDirection = $request->input('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        // Paginate
        $perPage = min($request->integer('per_page', 15), 100);
        $paginator = $query->paginate($perPage);

        // Transform to VoucherData DTOs
        $voucherData = new DataCollection(VoucherData::class, $paginator->items());

        return ApiResponse::success([
            'vouchers' => $voucherData,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'external_type' => 'nullable|string|max:255',
            'external_id' => 'nullable|string|max:255',
            'reference_id' => 'nullable|string|max:255',
            'user_id' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,redeemed,expired',
            'validation_status' => 'nullable|string|in:passed,failed,blocked',
            'order_by' => 'nullable|string|in:created_at,redeemed_at,expires_at,code',
            'order_direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
