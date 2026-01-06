<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $reportType = $request->input('type', 'disbursements');
        
        // Default to last 30 days
        $fromDate = $request->input('from_date', now()->subDays(30)->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));
        $status = $request->input('status');
        $rail = $request->input('rail');
        
        if ($reportType === 'settlements') {
            return $this->getSettlementReport($request, $fromDate, $toDate, $status);
        }
        
        return $this->getDisbursementReport($request, $fromDate, $toDate, $status, $rail);
    }
    
    private function getDisbursementReport(Request $request, string $fromDate, string $toDate, ?string $status, ?string $rail): Response
    {

        $query = DisbursementAttempt::query()
            ->whereBetween('attempted_at', [
                $fromDate,
                $toDate . ' 23:59:59',
            ])
            ->orderByDesc('attempted_at');

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($rail) {
            $query->where('settlement_rail', $rail);
        }

        $disbursements = $query->paginate(20);

        // Calculate summary stats
        $summaryQuery = DisbursementAttempt::query()
            ->whereBetween('attempted_at', [$fromDate, $toDate . ' 23:59:59']);

        if ($status) {
            $summaryQuery->where('status', $status);
        }

        if ($rail) {
            $summaryQuery->where('settlement_rail', $rail);
        }

        $totalCount = (clone $summaryQuery)->count();
        $successCount = (clone $summaryQuery)->success()->count();
        $failedCount = (clone $summaryQuery)->failed()->count();
        $totalAmount = (clone $summaryQuery)->sum('amount');
        $successAmount = (clone $summaryQuery)->success()->sum('amount');
        $successRate = $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0;

        return Inertia::render('reports/Index', [
            'report_type' => 'disbursements',
            'disbursements' => $disbursements,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'status' => $status,
                'rail' => $rail,
            ],
            'summary' => [
                'total_count' => $totalCount,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'total_amount' => $totalAmount,
                'success_amount' => $successAmount,
                'success_rate' => $successRate,
            ],
        ]);
    }
    
    private function getSettlementReport(Request $request, string $fromDate, string $toDate, ?string $status): Response
    {
        $query = Voucher::query()
            ->where('user_id', auth()->id())
            ->whereIn('voucher_type', ['payable', 'settlement'])
            ->whereBetween('created_at', [
                $fromDate,
                $toDate . ' 23:59:59',
            ])
            ->orderByDesc('created_at');
        
        // Apply status filter
        if ($status) {
            $query->where('state', $status);
        }
        
        $settlements = $query->paginate(20)->through(function ($voucher) {
            return [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'type' => $voucher->voucher_type,
                'state' => $voucher->state,
                'target_amount' => $voucher->target_amount,
                'paid_total' => $voucher->getPaidTotal(),
                'redeemed_total' => $voucher->getRedeemedTotal(),
                'remaining' => $voucher->getRemaining(),
                'currency' => 'PHP',
                'created_at' => $voucher->created_at->toIso8601String(),
                'closed_at' => $voucher->closed_at?->toIso8601String(),
            ];
        });
        
        // Calculate summary stats
        $summaryQuery = Voucher::query()
            ->where('user_id', auth()->id())
            ->whereIn('voucher_type', ['payable', 'settlement'])
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59']);
        
        if ($status) {
            $summaryQuery->where('state', $status);
        }
        
        $allVouchers = $summaryQuery->get();
        $totalCount = $allVouchers->count();
        $activeCount = $allVouchers->where('state', 'active')->count();
        $closedCount = $allVouchers->where('state', 'closed')->count();
        $totalTarget = $allVouchers->sum('target_amount');
        $totalCollected = $allVouchers->sum(fn($v) => $v->getPaidTotal());
        $collectionRate = $totalTarget > 0 ? round(($totalCollected / $totalTarget) * 100, 2) : 0;
        
        return Inertia::render('reports/Index', [
            'report_type' => 'settlements',
            'settlements' => $settlements,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'status' => $status,
            ],
            'summary' => [
                'total_count' => $totalCount,
                'active_count' => $activeCount,
                'closed_count' => $closedCount,
                'total_target' => $totalTarget,
                'total_collected' => $totalCollected,
                'collection_rate' => $collectionRate,
            ],
        ]);
    }
}
