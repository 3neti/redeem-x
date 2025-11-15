<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\PaymentGateway\Models\Merchant;

class MerchantProfileController extends Controller
{
    public function __construct(
        private MerchantService $merchantService
    ) {}

    /**
     * Get merchant profile for authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        $merchant = $this->merchantService->getMerchantProfile($request->user());

        if (!$merchant) {
            // Auto-create if doesn't exist
            $merchant = $request->user()->getOrCreateMerchant();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'merchant' => $merchant,
                'categories' => $this->merchantService->getCategoryCodes(),
            ],
        ]);
    }

    /**
     * Update merchant profile for authenticated user.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'merchant_category_code' => ['sometimes', 'string', 'size:4'],
            'default_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'allow_tip' => ['nullable', 'boolean'],
        ]);

        $data = $this->merchantService->validateMerchantData($validated);
        $merchant = $this->merchantService->updateMerchantProfile($request->user(), $data);

        return response()->json([
            'success' => true,
            'data' => $merchant,
            'message' => 'Merchant profile updated successfully',
        ]);
    }
}
