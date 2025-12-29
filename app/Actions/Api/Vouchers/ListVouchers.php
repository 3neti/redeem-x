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
 * List user's vouchers via API.
 *
 * Endpoint: GET /api/v1/vouchers
 */
#[Group('Vouchers')]
class ListVouchers
{
    use AsAction;

    /**
     * List user's vouchers
     * 
     * Get a paginated list of vouchers with optional filtering by status and search.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $status = $request->input('status'); // 'active', 'redeemed', 'expired'
        $search = $request->input('search'); // Search by code

        $query = $request->user()
            ->vouchers()
            ->latest();

        // Filter by status
        if ($status === 'redeemed') {
            $query->whereNotNull('redeemed_at');
        } elseif ($status === 'expired') {
            $query->where('expires_at', '<', now())
                ->whereNull('redeemed_at');
        } elseif ($status === 'active') {
            $query->whereNull('redeemed_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        }

        // Search by code
        if ($search) {
            $query->where('code', 'like', "%{$search}%");
        }

        $vouchers = $query->paginate($perPage);

        // Transform to VoucherData DTOs using DataCollection
        $voucherData = new DataCollection(VoucherData::class, $vouchers->items());

        return ApiResponse::success([
            'data' => $voucherData,
            'pagination' => [
                'current_page' => $vouchers->currentPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'last_page' => $vouchers->lastPage(),
                'from' => $vouchers->firstItem(),
                'to' => $vouchers->lastItem(),
            ],
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|in:active,redeemed,expired',
            'search' => 'nullable|string|max:255',
        ];
    }
}
