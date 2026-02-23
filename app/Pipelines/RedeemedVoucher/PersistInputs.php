<?php

namespace App\Pipelines\RedeemedVoucher;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * PersistInputs is a pipeline stage that saves redemption inputs from
 * redeemer metadata to the voucher's inputs table.
 *
 * Reads all inputs from metadata['redemption']['inputs'] and saves them
 * using the HasInputs trait's forceSetInput() method.
 *
 * Handles special cases:
 * - Location from bot: {lat, lng} → expanded to latitude, longitude, address
 * - KYC from bot: {status, transaction_id, ...} → expanded to kyc_status, kyc_transaction_id, etc.
 */
class PersistInputs
{
    private const DEBUG = false;

    /**
     * Handle the persistence of redemption inputs to the voucher inputs table.
     *
     * This method:
     *  - Retrieves the first redeemer of the voucher
     *  - Loads inputs from metadata['redemption']['inputs']
     *  - Normalizes array inputs (location, kyc) to flat fields
     *  - Saves each input to the voucher inputs table using forceSetInput()
     *
     * @param  \LBHurtado\Voucher\Models\Voucher  $voucher
     * @return mixed
     */
    public function handle($voucher, Closure $next)
    {
        $redeemer = $voucher->redeemers->first();

        if (! $redeemer) {
            if (self::DEBUG) {
                Log::debug('[PersistInputs] No redeemer found; skipping', [
                    'voucher' => $voucher->code,
                ]);
            }

            return $next($voucher);
        }

        $metadata = $redeemer->metadata['redemption'] ?? [];
        $inputs = $metadata['inputs'] ?? [];

        if (self::DEBUG) {
            Log::debug('[PersistInputs] Loaded redemption metadata', [
                'voucher' => $voucher->code,
                'inputs' => array_keys($inputs),
            ]);
        }

        // Normalize inputs (expand arrays to flat fields)
        $inputs = $this->normalizeInputs($inputs);

        // Save each input to voucher inputs table using the HasInputs trait
        foreach ($inputs as $name => $value) {
            if (empty($value) && $value !== '0' && $value !== 0) {
                if (self::DEBUG) {
                    Log::debug('[PersistInputs] Skipping empty input', [
                        'voucher' => $voucher->code,
                        'input' => $name,
                    ]);
                }

                continue;
            }

            // Skip array values that weren't expanded
            if (is_array($value)) {
                Log::warning('[PersistInputs] Skipping array input (not expanded)', [
                    'voucher' => $voucher->code,
                    'input' => $name,
                    'keys' => array_keys($value),
                ]);

                continue;
            }

            try {
                // Use forceSetInput to save to inputs table (bypasses validation)
                $voucher->forceSetInput($name, (string) $value);

                Log::info('[PersistInputs] Saved input to voucher', [
                    'voucher' => $voucher->code,
                    'input' => $name,
                    'value_length' => is_string($value) ? strlen($value) : 0,
                ]);
            } catch (\Exception $e) {
                Log::error('[PersistInputs] Failed to save input', [
                    'voucher' => $voucher->code,
                    'input' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (self::DEBUG) {
            Log::debug('[PersistInputs] Completed persisting inputs', [
                'voucher' => $voucher->code,
            ]);
        }

        return $next($voucher);
    }

    /**
     * Normalize inputs by expanding array values to flat fields.
     *
     * Handles:
     * - location: {lat, lng} → latitude, longitude
     * - kyc: {status, transaction_id, ...} → kyc_status, kyc_transaction_id, ...
     *
     * @param  array  $inputs  Raw inputs from metadata
     * @return array Normalized inputs with flat fields
     */
    protected function normalizeInputs(array $inputs): array
    {
        $normalized = [];

        foreach ($inputs as $name => $value) {
            // Handle location array from bot
            if ($name === 'location' && is_array($value)) {
                // Bot format: {lat, lng} or {latitude, longitude}
                if (isset($value['lat'])) {
                    $normalized['latitude'] = (string) $value['lat'];
                } elseif (isset($value['latitude'])) {
                    $normalized['latitude'] = (string) $value['latitude'];
                }

                if (isset($value['lng'])) {
                    $normalized['longitude'] = (string) $value['lng'];
                } elseif (isset($value['longitude'])) {
                    $normalized['longitude'] = (string) $value['longitude'];
                }

                // Copy any other location fields (accuracy, address, map, etc.)
                foreach (['accuracy', 'address', 'map', 'timestamp', 'formatted_address'] as $field) {
                    if (isset($value[$field])) {
                        $normalized[$field] = (string) $value[$field];
                    }
                }

                Log::info('[PersistInputs] Expanded location array', [
                    'original' => array_keys($value),
                    'expanded' => array_keys(array_filter($normalized, fn ($k) => in_array($k, ['latitude', 'longitude', 'accuracy', 'address', 'map', 'timestamp', 'formatted_address']), ARRAY_FILTER_USE_KEY)),
                ]);

                continue;
            }

            // Handle KYC array from bot
            if ($name === 'kyc' && is_array($value)) {
                // Prefix KYC fields to avoid conflicts
                foreach ($value as $kycField => $kycValue) {
                    if (! empty($kycValue)) {
                        $normalized['kyc_'.$kycField] = is_string($kycValue) ? $kycValue : json_encode($kycValue);
                    }
                }

                Log::info('[PersistInputs] Expanded kyc array', [
                    'original' => array_keys($value),
                    'expanded' => array_filter(array_keys($normalized), fn ($k) => str_starts_with($k, 'kyc_')),
                ]);

                continue;
            }

            // Pass through non-array values
            $normalized[$name] = $value;
        }

        return $normalized;
    }
}
