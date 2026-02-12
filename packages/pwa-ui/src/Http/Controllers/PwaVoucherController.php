<?php

namespace LBHurtado\PwaUi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PwaVoucherController extends Controller
{
    /**
     * Display voucher list.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all'); // all, redeemed, redeemable

        $query = $user->vouchers()->latest();

        if ($filter === 'redeemed') {
            $query->whereNotNull('redeemed_at');
        } elseif ($filter === 'redeemable') {
            $query->whereNull('redeemed_at');
        }

        $vouchers = $query->paginate(20)->through(function ($voucher) {
            $amount = $voucher->cash?->amount ?? 0;
            $amountFloat = is_numeric($amount) ? (float) $amount : 0;
            
            return [
                'code' => $voucher->code,
                'amount' => $amountFloat,
                'currency' => $voucher->cash?->currency ?? 'PHP',
                'status' => $voucher->status,
                'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                'created_at' => $voucher->created_at->toIso8601String(),
            ];
        });

        return Inertia::render('Pwa/Vouchers/Index', [
            'vouchers' => $vouchers,
            'filter' => $filter,
        ]);
    }

    /**
     * Display voucher detail with QR and share options.
     */
    public function show(Request $request, string $code): Response
    {
        $voucher = $request->user()
            ->vouchers()
            ->where('code', $code)
            ->firstOrFail();

        $amount = $voucher->cash?->amount ?? 0;
        $amountFloat = is_numeric($amount) ? (float) $amount : 0;
        
        return Inertia::render('Pwa/Vouchers/Show', [
            'voucher' => [
                'code' => $voucher->code,
                'amount' => $amountFloat,
                'currency' => $voucher->cash?->currency ?? 'PHP',
                'status' => $voucher->status,
                'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                'created_at' => $voucher->created_at->toIso8601String(),
                'redeem_url' => route('redeem.start', ['code' => $code]),
            ],
        ]);
    }

    /**
     * Show voucher generation form.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Pwa/Vouchers/Generate', [
            'campaigns' => [], // TODO: Load user campaigns
            'inputFieldOptions' => [], // TODO: Load from config
        ]);
    }

    /**
     * Store generated vouchers.
     */
    public function store(Request $request)
    {
        // TODO: Implement voucher generation
        // This will call the existing GenerateVouchers action
        return back()->with('success', 'Vouchers generated successfully');
    }
}
