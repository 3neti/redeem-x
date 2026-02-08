<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\CalculateCharge;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class ChargeCalculationController extends Controller
{
    public function __invoke(Request $request, CalculateCharge $action): JsonResponse
    {
        try {
            // Validate incoming instructions data
            $validated = $request->validate([
                'cash' => 'required|array',
                'cash.amount' => 'required|numeric|min:0',
                'cash.currency' => 'nullable|string',
                'cash.validation' => 'nullable|array',
                'cash.validation.secret' => 'nullable|string',
                'cash.validation.mobile' => 'nullable|string',
                'cash.validation.payable' => 'nullable|string',
                'cash.validation.country' => 'nullable|string',
                'inputs' => 'nullable|array',
                'feedback' => 'nullable|array',
                'rider' => 'nullable|array',
                'validation' => 'nullable|array',
                'validation.location' => 'nullable|array',
                'validation.time' => 'nullable|array',
                'count' => 'nullable|integer|min:1',
                'prefix' => 'nullable|string',
                'mask' => 'nullable|string',
                'ttl' => 'nullable',
            ]);

            // Ensure required nested structures exist with defaults
            // Use recursive merge to preserve nested arrays
            $defaults = [
                'cash' => [
                    'amount' => 0,
                    'currency' => 'PHP',
                    'validation' => [],
                ],
                'inputs' => ['fields' => []],
                'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
                'rider' => ['message' => null, 'url' => null],
                'validation' => ['location' => null, 'time' => null],
                'count' => 1,
                'prefix' => '',
                'mask' => '',
                'ttl' => null,
            ];

            // Merge cash separately to preserve nested validation
            $data = array_merge($defaults, $validated);
            if (isset($validated['cash'])) {
                $data['cash'] = array_merge($defaults['cash'], $validated['cash']);
                // Preserve cash.validation from request (don't overwrite with empty array)
                if (isset($validated['cash']['validation'])) {
                    $data['cash']['validation'] = $validated['cash']['validation'];
                }
            }

            // Create VoucherInstructionsData from request
            $instructions = VoucherInstructionsData::from($data);

            // Calculate charges
            $breakdown = $action->handle($request->user(), $instructions);

            return response()->json([
                'breakdown' => $breakdown->breakdown,
                'total' => $breakdown->total,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to calculate charges',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
