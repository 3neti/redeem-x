<?php

namespace App\Http\Controllers\Pay;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\Log;

class PayVoucherController extends Controller
{
    /**
     * Show pay voucher page
     */
    public function index()
    {
        return inertia('Pay/Index');
    }

    /**
     * Get voucher quote (validate code, compute remaining)
     */
    public function quote(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $voucher = Voucher::where('code', strtoupper(trim($request->code)))->first();

        if (!$voucher) {
            return response()->json([
                'error' => 'Voucher not found',
            ], 404);
        }

        if (!$voucher->canAcceptPayment()) {
            return response()->json([
                'error' => 'This voucher cannot accept payments',
            ], 403);
        }

        $remaining = $voucher->getRemaining();
        $minAmount = $voucher->rules['min_payment_amount'] ?? 1.00;
        $maxAmount = min($remaining, $voucher->rules['max_payment_amount'] ?? $remaining);

        return response()->json([
            'voucher_code' => $voucher->code,
            'voucher_type' => $voucher->voucher_type->value,
            'target_amount' => $voucher->target_amount,
            'paid_total' => $voucher->getPaidTotal(),
            'remaining' => $remaining,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'allow_partial' => $voucher->rules['allow_partial_payments'] ?? true,
        ]);
    }

    /**
     * Generate QR code for payment
     * 
     * TODO: Implement NetBank Direct Checkout QR generation
     * Reuse existing top-up QR generation logic
     */
    public function generateQr(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $voucher = Voucher::where('code', strtoupper(trim($request->code)))->firstOrFail();

        if (!$voucher->canAcceptPayment()) {
            return response()->json([
                'error' => 'This voucher cannot accept payments',
            ], 403);
        }

        // TODO: Generate NetBank Direct Checkout QR
        // For now, return mock response
        return response()->json([
            'qr_code' => 'data:image/png;base64,mock-qr-code',
            'reference' => 'PAY-' . $voucher->code . '-' . time(),
            'amount' => $request->amount,
        ]);
    }
}
