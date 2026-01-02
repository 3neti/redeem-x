<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\PayWithVoucher;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Pay with voucher code.
     */
    public function voucher(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $result = PayWithVoucher::run(
                auth()->user(),
                $validated['code']
            );

            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
