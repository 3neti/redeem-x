<?php

declare(strict_types=1);

namespace App\Http\Controllers\Withdraw;

use App\Actions\Voucher\WithdrawFromVoucher;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Withdraw Controller — handles subsequent slice withdrawals from divisible vouchers.
 *
 * Public (no authentication required). The redeemer proves identity by providing
 * the mobile number that matches the original redeemer contact.
 *
 * Flow:
 * 1. GET /withdraw?code=CODE — show voucher info + withdrawal form
 * 2. POST /withdraw/{voucher:code} — validate mobile, execute withdrawal
 * 3. GET /withdraw/{voucher:code}/success — confirmation page
 */
class WithdrawController extends Controller
{
    /**
     * Show the withdrawal page for a divisible voucher.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $code = $request->query('code');

        if (! $code) {
            return redirect()->route('disburse.start');
        }

        $code = strtoupper(trim($code));

        try {
            $voucher = Voucher::where('code', $code)->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('disburse.start')
                ->withInput(['code' => $code])
                ->withErrors(['code' => 'Invalid voucher code.']);
        }

        if (! $voucher->isDivisible()) {
            return redirect()->route('disburse.start')
                ->withInput(['code' => $code])
                ->withErrors(['code' => 'This voucher does not support partial withdrawals.']);
        }

        if (! $voucher->canWithdraw()) {
            return redirect()->route('disburse.start')
                ->withInput(['code' => $code])
                ->withErrors(['code' => 'This voucher has been fully consumed.']);
        }

        $cash = $voucher->instructions->cash;

        return Inertia::render('withdraw/Withdraw', [
            'voucher' => [
                'code' => $voucher->code,
                'amount' => $cash->amount,
                'currency' => $cash->currency,
                'formatted_amount' => Number::currency($cash->amount, $cash->currency),
                'slice_mode' => $cash->slice_mode,
                'slice_amount' => $voucher->getSliceAmount(),
                'formatted_slice_amount' => $cash->slice_mode === 'fixed'
                    ? Number::currency($voucher->getSliceAmount(), $cash->currency)
                    : null,
                'max_slices' => $voucher->getMaxSlices(),
                'min_withdrawal' => $voucher->getMinWithdrawal(),
                'consumed_slices' => $voucher->getConsumedSlices(),
                'remaining_slices' => $voucher->getRemainingSlices(),
                'remaining_balance' => $voucher->getRemainingBalance(),
                'formatted_remaining' => Number::currency($voucher->getRemainingBalance(), $cash->currency),
            ],
        ]);
    }

    /**
     * Process a slice withdrawal.
     */
    public function process(Voucher $voucher, Request $request): RedirectResponse
    {
        $request->validate([
            'mobile' => 'required|string',
            'amount' => 'nullable|numeric|min:1',
        ]);

        $mobile = $request->input('mobile');

        // Look up the original redeemer contact by matching mobile
        $contact = $voucher->contact;

        if (! $contact) {
            return back()->withErrors(['mobile' => 'This voucher has not been redeemed yet.']);
        }

        // Normalize mobile for comparison (strip +63 prefix, compare last 10 digits)
        $normalizedInput = preg_replace('/\D/', '', $mobile);
        $normalizedContact = preg_replace('/\D/', '', $contact->mobile ?? '');

        // Compare last 10 digits (handles +639xx vs 09xx vs 9xx)
        $inputSuffix = substr($normalizedInput, -10);
        $contactSuffix = substr($normalizedContact, -10);

        if ($inputSuffix !== $contactSuffix) {
            return back()->withErrors(['mobile' => 'Mobile number does not match the original redeemer.']);
        }

        $amount = $voucher->getSliceMode() === 'open'
            ? (float) $request->input('amount')
            : null;

        try {
            $result = WithdrawFromVoucher::run($voucher, $contact, $amount);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            Log::warning('[WithdrawController] Withdrawal failed', [
                'voucher' => $voucher->code,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return redirect()->route('withdraw.success', ['voucher' => $voucher->code])
            ->with('withdrawal_result', $result);
    }

    /**
     * Show the withdrawal success page.
     */
    public function success(Voucher $voucher): Response|RedirectResponse
    {
        $result = session('withdrawal_result');

        if (! $result) {
            // No result in session — redirect back to withdraw page
            return redirect()->route('withdraw.show', ['code' => $voucher->code]);
        }

        $cash = $voucher->instructions->cash;

        return Inertia::render('withdraw/Success', [
            'voucher' => [
                'code' => $voucher->code,
                'currency' => $cash->currency,
                'slice_mode' => $cash->slice_mode,
            ],
            'result' => [
                'amount' => $result['amount'],
                'formatted_amount' => Number::currency($result['amount'], $cash->currency),
                'slice_number' => $result['slice_number'],
                'remaining_slices' => $result['remaining_slices'],
                'remaining_balance' => $result['remaining_balance'],
                'formatted_remaining' => Number::currency($result['remaining_balance'], $cash->currency),
                'can_withdraw_more' => $voucher->fresh()->canWithdraw(),
            ],
        ]);
    }
}
