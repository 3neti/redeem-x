<?php

namespace App\Services\DataEnrichers;

use Illuminate\Support\Facades\Log;

/**
 * Default fallback data enricher for unknown gateways.
 *
 * This enricher is used when no gateway-specific enricher is available.
 * It simply logs that raw data is available but doesn't extract any
 * specific fields, preserving the raw response for manual inspection.
 */
class DefaultDataEnricher extends AbstractDataEnricher
{
    /**
     * Check if this enricher supports the given gateway.
     *
     * Always returns true as this is the fallback enricher.
     *
     * @param  string  $gateway  Gateway name
     */
    public function supports(string $gateway): bool
    {
        return true; // Fallback for all unknown gateways
    }

    /**
     * Extract rich data from gateway API response.
     *
     * For unknown gateways, this just logs that raw data is available
     * without attempting to extract specific fields.
     *
     * @param  array  &$metadata  Voucher metadata (passed by reference)
     * @param  array  $raw  Raw gateway response
     */
    public function extract(array &$metadata, array $raw): void
    {
        $gateway = $metadata['disbursement']['gateway'] ?? 'unknown';

        Log::debug("[DefaultEnricher] Raw data available for {$gateway}", [
            'gateway' => $gateway,
            'has_data' => ! empty($raw),
            'raw_keys' => array_keys($raw),
        ]);

        // Don't extract anything specific - leave raw data intact in status_raw
        // Future developers can implement gateway-specific enrichers as needed
    }
}
