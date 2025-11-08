<?php

namespace App\Http\Controllers;

use LBHurtado\Voucher\Actions\GenerateVouchers;
use App\Http\Requests\VoucherGenerationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VoucherGenerationController extends Controller
{
    /**
     * Show the voucher generation form.
     */
    public function create(): Response
    {
        return Inertia::render('Vouchers/Generate/Create', [
            'wallet_balance' => auth()->user()->balanceFloatNum,
            'input_field_options' => $this->getInputFieldOptions(),
        ]);
    }

    /**
     * Generate vouchers based on instructions.
     */
    public function store(VoucherGenerationRequest $request): RedirectResponse
    {
        $vouchers = GenerateVouchers::run($request->toInstructions());
        
        $count = $vouchers->count();

        return redirect()
            ->route('vouchers.generate.success', ['count' => $count])
            ->with('success', sprintf(
                'Successfully generated %d voucher%s',
                $count,
                $count > 1 ? 's' : ''
            ));
    }

    /**
     * Show voucher generation success page.
     */
    public function success(int $count): Response
    {
        // Get the latest N vouchers for this user
        $vouchers = auth()->user()
            ->vouchers()
            ->latest()
            ->take($count)
            ->get();

        if ($vouchers->isEmpty()) {
            abort(404, 'No vouchers found');
        }

        return Inertia::render('Vouchers/Generate/Success', [
            'vouchers' => $vouchers->map(function($voucher) {
                return [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'amount' => $voucher->instructions->cash->amount ?? 0,
                    'currency' => $voucher->instructions->cash->currency ?? 'PHP',
                    'status' => $voucher->status ?? 'active',
                    'expires_at' => $voucher->expires_at?->toIso8601String(),
                    'created_at' => $voucher->created_at->toIso8601String(),
                ];
            }),
            'batch_id' => 'batch-' . now()->timestamp,
            'count' => $vouchers->count(),
            'total_value' => $vouchers->sum(function($v) {
                return $v->instructions->cash->amount ?? 0;
            }),
        ]);
    }

    /**
     * Get input field options for the form.
     */
    protected function getInputFieldOptions(): array
    {
        return \LBHurtado\Voucher\Enums\VoucherInputField::options();
    }
}
