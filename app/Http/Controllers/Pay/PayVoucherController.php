<?php

namespace App\Http\Controllers\Pay;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use LBHurtado\Voucher\Models\Voucher;

class PayVoucherController extends Controller
{
    /**
     * Show pay voucher page
     *
     * Note: This is a public endpoint. Feature check uses global config,
     * not per-user flags since payers are typically unauthenticated.
     */
    public function index()
    {
        // Check if settlement vouchers feature is enabled globally
        // In local/staging: Always enabled
        // In production: Requires APP_ENV=production + feature enabled in config
        $enabled = app()->environment('local', 'staging') ||
                   config('pay.enabled', false);

        if (! $enabled) {
            abort(404, 'Settlement vouchers feature is not available');
        }

        $code = request()->query('code');

        return inertia('pay/Index', [
            'pay' => config('pay'),
            'initial_code' => $code,
        ]);
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

        if (! $voucher) {
            return response()->json([
                'error' => 'Voucher not found',
            ], 404);
        }

        if (! $voucher->canAcceptPayment()) {
            return response()->json([
                'error' => 'This voucher cannot accept payments',
            ], 403);
        }

        $remaining = $voucher->getRemaining();
        $minAmount = $voucher->rules['min_payment_amount'] ?? 1.00;
        $maxAmount = min($remaining, $voucher->rules['max_payment_amount'] ?? $remaining);

        // Get attachments
        $attachments = $voucher->getMedia('voucher_attachments')->map(function ($media) {
            return [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'human_readable_size' => $media->human_readable_size,
                'url' => $media->getUrl(),
            ];
        });

        return response()->json([
            'voucher_code' => $voucher->code,
            'voucher_type' => $voucher->voucher_type->value,
            'target_amount' => $voucher->target_amount,
            'paid_total' => $voucher->getPaidTotal(),
            'remaining' => $remaining,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'allow_partial' => $voucher->rules['allow_partial_payments'] ?? true,
            'external_metadata' => $voucher->external_metadata, // Freeform JSON for display
            'attachments' => $attachments,
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

        if (! $voucher->canAcceptPayment()) {
            return response()->json([
                'error' => 'This voucher cannot accept payments',
            ], 403);
        }

        // TODO: Generate NetBank Direct Checkout QR
        // For now, return mock response
        return response()->json([
            'qr_code' => 'data:image/png;base64,mock-qr-code',
            'reference' => 'PAY-'.$voucher->code.'-'.time(),
            'amount' => $request->amount,
        ]);
    }
}
