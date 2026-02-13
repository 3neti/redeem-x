<?php

namespace LBHurtado\PwaUi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Enums\VoucherInputField;

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
            // Determine amount based on voucher type
            $amount = match($voucher->voucher_type->value) {
                'payable' => $voucher->target_amount ?? 0,
                'settlement' => $voucher->cash?->amount ?? 0, // Show loan amount
                default => $voucher->cash?->amount ?? 0, // Redeemable
            };
            $amountFloat = is_numeric($amount) ? (float) $amount : 0;
            
            return [
                'code' => $voucher->code,
                'amount' => $amountFloat,
                'target_amount' => $voucher->target_amount ? (float) $voucher->target_amount : null,
                'voucher_type' => $voucher->voucher_type->value,
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

        // Determine amount based on voucher type
        $amount = match($voucher->voucher_type->value) {
            'payable' => $voucher->target_amount ?? 0,
            'settlement' => $voucher->cash?->amount ?? 0, // Show loan amount
            default => $voucher->cash?->amount ?? 0, // Redeemable
        };
        $amountFloat = is_numeric($amount) ? (float) $amount : 0;
        
        return Inertia::render('Pwa/Vouchers/Show', [
            'voucher' => [
                'code' => $voucher->code,
                'amount' => $amountFloat,
                'target_amount' => $voucher->target_amount ? (float) $voucher->target_amount : null,
                'voucher_type' => $voucher->voucher_type->value,
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
        $user = $request->user();
        
        // Load user campaigns
        $campaigns = $user ? Campaign::where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn($campaign) => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'instructions' => $campaign->instructions->toArray(),
            ])
            ->toArray() : [];
        
        // Load input field options from VoucherInputField enum
        $inputFieldOptions = VoucherInputField::options();
        
        // Get wallet balance
        $walletBalance = $user ? (float) $user->balance : 0;
        $formattedBalance = '₱' . number_format($walletBalance, 2);
        
        return Inertia::render('Pwa/Vouchers/Generate', [
            'campaigns' => $campaigns,
            'inputFieldOptions' => $inputFieldOptions,
            'walletBalance' => $walletBalance,
            'formattedBalance' => $formattedBalance,
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
