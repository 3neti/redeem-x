<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        // Default to last 30 days
        $fromDate = $request->input('from_date', now()->subDays(30)->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));
        $status = $request->input('status');
        $rail = $request->input('rail');

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
}
