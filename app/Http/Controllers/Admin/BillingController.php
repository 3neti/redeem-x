<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VoucherGenerationCharge;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = VoucherGenerationCharge::query()
            ->with('user:id,name,email', 'campaign:id,name')
            ->latest('generated_at');

        // Filter by user if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

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
                'user' => [
                    'id' => $charge->user->id,
                    'name' => $charge->user->name,
                    'email' => $charge->user->email,
                ],
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

        return Inertia::render('admin/billing/Index', [
            'charges' => $charges,
            'filters' => $request->only(['user_id', 'from', 'to']),
        ]);
    }

    public function show(VoucherGenerationCharge $charge): Response
    {
        $charge->load('user:id,name,email', 'campaign:id,name');

        return Inertia::render('admin/billing/Show', [
            'charge' => [
                'id' => $charge->id,
                'user' => [
                    'id' => $charge->user->id,
                    'name' => $charge->user->name,
                    'email' => $charge->user->email,
                ],
                'campaign' => $charge->campaign ? [
                    'id' => $charge->campaign->id,
                    'name' => $charge->campaign->name,
                ] : null,
                'voucher_codes' => $charge->voucher_codes,
                'voucher_count' => $charge->voucher_count,
                'instructions_snapshot' => $charge->instructions_snapshot,
                'charge_breakdown' => $charge->charge_breakdown,
                'total_charge' => '₱'.number_format($charge->total_charge, 2),
                'charge_per_voucher' => '₱'.number_format($charge->charge_per_voucher, 2),
                'generated_at' => $charge->generated_at->toIso8601String(),
                'created_at' => $charge->created_at->toIso8601String(),
            ],
        ]);
    }
}
