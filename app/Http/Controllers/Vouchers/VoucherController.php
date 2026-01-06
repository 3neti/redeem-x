<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vouchers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;
use Laravel\Pennant\Feature;

/**
 * Voucher Management Controller
 *
 * Handles listing, viewing, and exporting vouchers for authenticated users.
 */
class VoucherController extends Controller
{
    /**
     * Display the vouchers page.
     *
     * Data is loaded via API from the frontend.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('vouchers/Index');
    }

    /**
     * Display the specified voucher.
     */
    public function show(Voucher $voucher): Response
    {
        // Load relationships including inputs (single source of truth)
        $voucher->load(['owner', 'inputs']);

        $data = [
            'voucher' => VoucherData::fromModel($voucher),
            'input_field_options' => VoucherInputField::options(),
        ];

        // Add settlement data if feature is enabled
        if (Feature::active('settlement-vouchers') && $voucher->voucher_type) {
            $data['settlement'] = [
                'type' => $voucher->voucher_type->value,
                'state' => $voucher->state->value,
                'target_amount' => $voucher->target_amount,
                'paid_total' => $voucher->getPaidTotal(),
                'redeemed_total' => $voucher->getRedeemedTotal(),
                'remaining' => $voucher->getRemaining(),
                'available_balance' => $voucher->cash?->balanceFloat ?? 0,
                'can_accept_payment' => $voucher->canAcceptPayment(),
                'can_redeem' => $voucher->canRedeem(),
                'is_locked' => $voucher->isLocked(),
                'is_closed' => $voucher->isClosed(),
                'is_expired' => $voucher->isExpired(),
                'locked_at' => $voucher->locked_at?->toIso8601String(),
                'closed_at' => $voucher->closed_at?->toIso8601String(),
                'rules' => $voucher->rules,
            ];
        }

        return Inertia::render('vouchers/Show', $data);
    }
}
