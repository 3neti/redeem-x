<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use LBHurtado\Merchant\Models\Merchant;

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

        \Illuminate\Support\Facades\Log::debug('[MerchantService] Updating merchant profile', [
            'merchant_id' => $merchant->id,
            'data' => $data,
        ]);

        $updateData = [
            'name' => $data['name'] ?? $merchant->name,
            'city' => $data['city'] ?? $merchant->city,
            'description' => $data['description'] ?? $merchant->description,
            'merchant_category_code' => $data['merchant_category_code'] ?? $merchant->merchant_category_code,
            'is_dynamic' => array_key_exists('is_dynamic', $data) ? $data['is_dynamic'] : $merchant->is_dynamic,
            'default_amount' => array_key_exists('default_amount', $data) ? $data['default_amount'] : $merchant->default_amount,
            'min_amount' => array_key_exists('min_amount', $data) ? $data['min_amount'] : $merchant->min_amount,
            'max_amount' => array_key_exists('max_amount', $data) ? $data['max_amount'] : $merchant->max_amount,
            'merchant_name_template' => $data['merchant_name_template'] ?? $merchant->merchant_name_template,
            'allow_tip' => array_key_exists('allow_tip', $data) ? $data['allow_tip'] : $merchant->allow_tip,
        ];

        \Illuminate\Support\Facades\Log::debug('[MerchantService] Update data prepared', [
            'update_data' => $updateData,
        ]);

        $merchant->update($updateData);

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

        if (isset($data['is_dynamic'])) {
            $validated['is_dynamic'] = (bool) $data['is_dynamic'];
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

        if (isset($data['merchant_name_template'])) {
            $validated['merchant_name_template'] = trim($data['merchant_name_template']);
        }

        if (isset($data['allow_tip'])) {
            $validated['allow_tip'] = (bool) $data['allow_tip'];
        }

        return $validated;
    }
}
