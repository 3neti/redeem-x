<?php

namespace App\Http\Controllers\Api;

use App\Actions\CalculateChargeAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class ChargeCalculationController extends Controller
{
    public function __invoke(Request $request, CalculateChargeAction $action): JsonResponse
    {
        try {
            // Validate incoming instructions data
            $validated = $request->validate([
                'cash' => 'required|array',
                'cash.amount' => 'required|numeric|min:0',
                'cash.currency' => 'nullable|string',
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
            $data = array_merge([
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
            ], $validated);

            // Ensure nested validation exists
            if (isset($data['cash']) && !isset($data['cash']['validation'])) {
                $data['cash']['validation'] = [];
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
