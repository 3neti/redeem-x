<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Models\PaymentRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Get Pending Payment Requests
 *
 * Fetch payment requests awaiting confirmation for a voucher.
 * Only the voucher owner can view pending requests.
 *
 * @group Vouchers
 *
 * @authenticated
 */
#[Group('Vouchers')]
class GetPendingPaymentRequests
{
    /**
     * Get pending payment requests for a voucher
     *
     * Returns payment requests in 'awaiting_confirmation' status.
     */
    public function __invoke(string $code, Request $request): JsonResponse
    {
        $user = $request->user();

        // Find voucher with owner
        $voucher = Voucher::with('owner')->where('code', $code)->first();

        if (! $voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found',
            ], 404);
        }

        // Check ownership
        if (! $voucher->owner || $voucher->owner->id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this voucher',
            ], 403);
        }

        // Fetch pending payment requests
        $pendingRequests = PaymentRequest::where('voucher_id', $voucher->id)
            ->awaitingConfirmation()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'reference_id' => $request->reference_id,
                    'amount' => $request->getAmountInMajorUnits(),
                    'currency' => $request->currency,
                    'payer_info' => $request->payer_info,
                    'status' => $request->status,
                    'created_at' => $request->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $pendingRequests,
        ]);
    }
}
