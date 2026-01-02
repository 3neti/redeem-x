<?php

declare(strict_types=1);

namespace App\Actions\Api\Dashboard;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use App\Models\TopUp;
use App\Models\VoucherGenerationCharge;

/**
 * Get recent activity for dashboard.
 *
 * Endpoint: GET /api/v1/dashboard/activity
 */
class GetRecentActivity
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();

        // Recent vouchers generated (from generation charges)
        $recentGenerations = VoucherGenerationCharge::where('user_id', $user->id)
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(function ($charge) {
                // Get voucher amount from instructions snapshot (JSON column)
                $instructions = is_array($charge->instructions_snapshot) 
                    ? $charge->instructions_snapshot 
                    : json_decode($charge->instructions_snapshot, true);
                
                $voucherAmount = $instructions['cash']['amount'] ?? 0;
                $totalAmount = $voucherAmount * $charge->voucher_count;
                
                // Get voucher codes - show first code if multiple, or "Multiple" if > 3
                $codes = $charge->voucher_codes ?? [];
                $codeDisplay = match(count($codes)) {
                    0 => 'N/A',
                    1 => $codes[0],
                    2 => implode(', ', $codes),
                    3 => implode(', ', $codes),
                    default => $codes[0] . ' +' . (count($codes) - 1) . ' more'
                };
                
                return [
                    'id' => $charge->id,
                    'type' => 'generation',
                    'voucher_codes' => $codeDisplay,
                    'voucher_count' => $charge->voucher_count,
                    'amount' => (float) $totalAmount, // Total value of all vouchers generated
                    'total_charge' => (float) $charge->total_charge, // What user was charged
                    'currency' => 'PHP',
                    'generated_at' => $charge->generated_at->toIso8601String(),
                ];
            });

        // Recent redemptions
        $recentRedemptions = Voucher::where('owner_id', $user->id)
            ->whereNotNull('redeemed_at')
            ->latest('redeemed_at')
            ->limit(5)
            ->get()
            ->map(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'type' => 'redemption',
                    'code' => $voucher->code,
                    'amount' => $voucher->instructions->cash->amount ?? 0,
                    'currency' => 'PHP',
                    'mobile' => $voucher->contact?->mobile ?? 'N/A',
                    'status' => $this->getRedemptionStatus($voucher),
                    'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                ];
            });

        // Recent deposits (wallet transactions)
        $recentDeposits = $user->walletTransactions()
            ->where('type', 'deposit')
            ->where('amount', '>', 0)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => 'deposit',
                    'amount' => $tx->amount / 100, // Convert cents to pesos
                    'currency' => 'PHP',
                    'gateway' => $tx->meta['gateway'] ?? 'N/A',
                    'created_at' => $tx->created_at->toIso8601String(),
                ];
            });

        // Recent top-ups
        $recentTopUps = TopUp::where('user_id', $user->id)
            ->where('payment_status', 'PAID')
            ->latest('paid_at')
            ->limit(3)
            ->get()
            ->map(function ($topUp) {
                // Show institution if selected, otherwise show gateway name
                $displayName = $topUp->institution_code 
                    ?? ucfirst($topUp->gateway) 
                    ?? 'Manual';
                
                return [
                    'id' => $topUp->id,
                    'type' => 'topup',
                    'amount' => (float) $topUp->amount,
                    'currency' => $topUp->currency,
                    'gateway' => $topUp->gateway,
                    'institution' => $displayName,
                    'paid_at' => $topUp->paid_at->toIso8601String(),
                ];
            });

        return ApiResponse::success([
            'activity' => [
                'generations' => $recentGenerations,
                'redemptions' => $recentRedemptions,
                'deposits' => $recentDeposits,
                'topups' => $recentTopUps,
            ],
        ]);
    }

    private function getRedemptionStatus($voucher): string
    {
        $disbursement = $voucher->metadata['disbursement'] ?? null;
        
        if (!$disbursement) {
            return 'pending';
        }

        return $disbursement['status'] ?? 'completed';
    }
}
