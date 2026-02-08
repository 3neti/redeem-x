<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Http\Responses\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Spatie\LaravelData\DataCollection;

/**
 * List Voucher Redemption Transactions
 *
 * Retrieve paginated history of all redeemed vouchers with disbursement details.
 *
 * This endpoint shows completed voucher redemptions with their disbursement status,
 * bank information, settlement rail, and amounts. Essential for transaction monitoring,
 * reconciliation, and financial reporting.
 *
 * **Transaction Data Includes:**
 * - Voucher code and redemption timestamp
 * - Disbursement amount, bank, and account
 * - Settlement rail (INSTAPAY/PESONET)
 * - Disbursement status (success, pending, failed)
 * - Operation IDs for bank reconciliation
 *
 * **Use Cases:**
 * - Transaction history dashboard
 * - Financial reconciliation with bank statements
 * - Monitoring disbursement success rates
 * - Auditing and compliance reporting
 *
 * @group Transactions
 *
 * @authenticated
 */
#[Group('Transactions')]
class ListTransactions
{
    /**
     * List voucher redemption transactions.
     *
     * Retrieve paginated list of redeemed vouchers with comprehensive disbursement details and filtering options.
     */
    #[QueryParameter('per_page', description: '*optional* - Results per page (1-100). Use for controlling pagination size. Default: 20', type: 'integer', example: 20)]
    #[QueryParameter('date_from', description: '*optional* - Filter transactions from this date (YYYY-MM-DD format). Filters by redemption date. Example: 2024-01-01', type: 'string', example: '2024-01-01')]
    #[QueryParameter('date_to', description: '*optional* - Filter transactions until this date (YYYY-MM-DD format). Must be after or equal to date_from. Example: 2024-12-31', type: 'string', example: '2024-12-31')]
    #[QueryParameter('search', description: '*optional* - Search by voucher code. Supports partial matching. Example: "PROMO-AB12"', type: 'string', example: 'PROMO')]
    #[QueryParameter('bank', description: '*optional* - Filter by disbursement bank code. Valid codes: GXCHPHM2XXX (GCash), MBTCPHM2XXX (Maya), BOPIPHMM (BPI), etc. Example: "GXCHPHM2XXX"', type: 'string', example: 'GXCHPHM2XXX')]
    #[QueryParameter('rail', description: '*optional* - Filter by settlement rail. Valid values: "INSTAPAY" (real-time, ≤₱50k), "PESONET" (next day, ≤₱1M). Example: "INSTAPAY"', type: 'string', example: 'INSTAPAY')]
    #[QueryParameter('status', description: '*optional* - Filter by disbursement status. Common values: "success", "pending", "failed". Status values depend on payment gateway responses.', type: 'string', example: 'success')]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:50'],
            'rail' => ['nullable', 'string', 'in:INSTAPAY,PESONET'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $perPage = min($request->integer('per_page', 20), 100);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $bank = $request->input('bank');
        $rail = $request->input('rail');
        $status = $request->input('status');

        $query = $user->vouchers()
            ->with(['owner'])
            ->whereNotNull('redeemed_at')
            ->orderByDesc('redeemed_at');

        // Filter by date range
        if ($dateFrom) {
            $query->whereDate('redeemed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('redeemed_at', '<=', $dateTo);
        }

        // Search by code
        if ($search) {
            $query->where('code', 'like', "%{$search}%");
        }

        // Filter by bank (from disbursement metadata)
        if ($bank) {
            $query->whereJsonContains('metadata->disbursement->bank', $bank);
        }

        // Filter by rail (from disbursement metadata)
        if ($rail) {
            $query->whereJsonContains('metadata->disbursement->rail', $rail);
        }

        // Filter by status (from disbursement metadata)
        if ($status) {
            $query->whereJsonContains('metadata->disbursement->status', $status);
        }

        // Get current page from request
        $currentPage = $request->integer('page', 1);

        // Fetch all matching vouchers (we'll filter and paginate in PHP)
        // NOTE: We can't use whereJson comparison for redemption_type due to PostgreSQL
        // compatibility issues with parameter binding. Filter in PHP instead.
        $allVouchers = $query->get();

        // Filter out voucher_payment redemptions in PHP
        $filteredVouchers = $allVouchers->filter(function ($voucher) {
            $redemptionType = $voucher->metadata['redemption_type'] ?? null;

            return $redemptionType !== 'voucher_payment';
        });

        // Calculate pagination manually
        $total = $filteredVouchers->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($currentPage - 1) * $perPage;
        $paginatedVouchers = $filteredVouchers->slice($offset, $perPage)->values();

        // Calculate from/to indices
        $from = $total > 0 ? $offset + 1 : null;
        $to = $total > 0 ? min($offset + $perPage, $total) : null;

        // Transform to VoucherData DTOs using DataCollection
        $transactionData = new DataCollection(VoucherData::class, $paginatedVouchers->all());

        return ApiResponse::success([
            'data' => $transactionData,
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
                'bank' => $bank,
                'rail' => $rail,
                'status' => $status,
            ],
        ]);
    }
}
