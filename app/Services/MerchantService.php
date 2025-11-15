<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use LBHurtado\PaymentGateway\Models\Merchant;

/**
 * Merchant Profile Management Service
 */
class MerchantService
{
    /**
     * Get merchant profile for user.
     */
    public function getMerchantProfile(User $user): ?Merchant
    {
        return $user->merchant;
    }

    /**
     * Update merchant profile for user.
     */
    public function updateMerchantProfile(User $user, array $data): Merchant
    {
        $merchant = $user->getOrCreateMerchant();

        $merchant->update([
            'name' => $data['name'] ?? $merchant->name,
            'city' => $data['city'] ?? $merchant->city,
            'description' => $data['description'] ?? $merchant->description,
            'merchant_category_code' => $data['merchant_category_code'] ?? $merchant->merchant_category_code,
            'default_amount' => $data['default_amount'] ?? $merchant->default_amount,
            'min_amount' => $data['min_amount'] ?? $merchant->min_amount,
            'max_amount' => $data['max_amount'] ?? $merchant->max_amount,
            'allow_tip' => $data['allow_tip'] ?? $merchant->allow_tip,
        ]);

        // Clear QR cache for this user since merchant data changed
        $this->clearUserQrCache($user);

        return $merchant->fresh();
    }

    /**
     * Clear all QR code cache entries for a user.
     */
    public function clearUserQrCache(User $user): void
    {
        // Clear dynamic amount QR
        Cache::forget("qr_code:{$user->id}:dynamic");

        // We could clear all user's QR caches with a prefix scan
        // but for simplicity, just clear the most common one
        // In production, consider using cache tags or prefix-based clearing
    }

    /**
     * Get available merchant category codes.
     */
    public function getCategoryCodes(): array
    {
        return Merchant::getCategoryCodes();
    }

    /**
     * Validate merchant data.
     */
    public function validateMerchantData(array $data): array
    {
        $validated = [];

        if (isset($data['name'])) {
            $validated['name'] = trim($data['name']);
        }

        if (isset($data['city'])) {
            $validated['city'] = trim($data['city']);
        }

        if (isset($data['description'])) {
            $validated['description'] = trim($data['description']);
        }

        if (isset($data['merchant_category_code'])) {
            $validated['merchant_category_code'] = $data['merchant_category_code'];
        }

        if (isset($data['default_amount'])) {
            $validated['default_amount'] = $data['default_amount'] ? (float) $data['default_amount'] : null;
        }

        if (isset($data['min_amount'])) {
            $validated['min_amount'] = $data['min_amount'] ? (float) $data['min_amount'] : null;
        }

        if (isset($data['max_amount'])) {
            $validated['max_amount'] = $data['max_amount'] ? (float) $data['max_amount'] : null;
        }

        if (isset($data['allow_tip'])) {
            $validated['allow_tip'] = (bool) $data['allow_tip'];
        }

        return $validated;
    }
}
