<?php

namespace App\Pipelines\RedeemedVoucher;

use Illuminate\Support\Facades\Log;
use Closure;

/**
 * PersistInputs is a pipeline stage that saves redemption inputs from
 * redeemer metadata to the voucher's inputs table.
 *
 * Reads all inputs from metadata['redemption']['inputs'] and saves them
 * using the HasInputs trait's forceSetInput() method.
 */
class PersistInputs
{
    /**
     * Handle the persistence of redemption inputs to the voucher inputs table.
     *
     * This method:
     *  - Retrieves the first redeemer of the voucher
     *  - Loads inputs from metadata['redemption']['inputs']
     *  - Saves each input to the voucher inputs table using forceSetInput()
     *
     * @param  \LBHurtado\Voucher\Models\Voucher  $voucher
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($voucher, Closure $next)
    {
        $redeemer = $voucher->redeemers->first();

        if (! $redeemer) {
            Log::debug('[PersistInputs] No redeemer found; skipping', [
                'voucher' => $voucher->code,
            ]);
            return $next($voucher);
        }

        $metadata = $redeemer->metadata['redemption'] ?? [];
        $inputs = $metadata['inputs'] ?? [];

        Log::debug('[PersistInputs] Loaded redemption metadata', [
            'voucher'  => $voucher->code,
            'inputs' => array_keys($inputs),
        ]);

        // Save each input to voucher inputs table using the HasInputs trait
        foreach ($inputs as $name => $value) {
            if (empty($value)) {
                Log::debug('[PersistInputs] Skipping empty input', [
                    'voucher' => $voucher->code,
                    'input' => $name,
                ]);
                continue;
            }

            try {
                // Use forceSetInput to save to inputs table (bypasses validation)
                $voucher->forceSetInput($name, $value);
                
                Log::info('[PersistInputs] Saved input to voucher', [
                    'voucher' => $voucher->code,
                    'input' => $name,
                    'value_length' => strlen($value),
                ]);
            } catch (\Exception $e) {
                Log::error('[PersistInputs] Failed to save input', [
                    'voucher' => $voucher->code,
                    'input' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::debug('[PersistInputs] Completed persisting inputs', [
            'voucher' => $voucher->code,
        ]);

        return $next($voucher);
    }
}
