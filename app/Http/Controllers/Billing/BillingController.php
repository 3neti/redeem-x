<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->user()
            ->voucherGenerationCharges()
            ->with('campaign:id,name')
            ->latest('generated_at');

        // Filter by date range
        if ($request->has('from')) {
            $query->whereDate('generated_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('generated_at', '<=', $request->to);
        }

        $charges = $query
            ->paginate(20)
            ->through(fn ($charge) => [
                'id' => $charge->id,
                'campaign' => $charge->campaign ? [
                    'id' => $charge->campaign->id,
                    'name' => $charge->campaign->name,
                ] : null,
                'voucher_count' => $charge->voucher_count,
                'total_charge' => '₱'.number_format($charge->total_charge, 2),
                'charge_per_voucher' => '₱'.number_format($charge->charge_per_voucher, 2),
                'generated_at' => $charge->generated_at->toIso8601String(),
                'charge_breakdown' => $charge->charge_breakdown,
            ]);

        // Calculate summary statistics
        $summary = [
            'total_vouchers' => $request->user()
                ->voucherGenerationCharges()
                ->sum('voucher_count'),
            'total_charges' => $request->user()
                ->voucherGenerationCharges()
                ->sum('total_charge'),
            'current_month_charges' => $request->user()
                ->monthlyCharges(now()->year, now()->month)
                ->sum('total_charge'),
        ];

        return Inertia::render('billing/Index', [
            'charges' => $charges,
            'summary' => $summary,
            'filters' => $request->only(['from', 'to']),
        ]);
    }
}
