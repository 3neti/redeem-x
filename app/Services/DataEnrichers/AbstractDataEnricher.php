<?php

namespace App\Services\DataEnrichers;

/**
 * Abstract base class for gateway-specific data enrichers.
 * 
 * Data enrichers extract rich metadata from gateway API responses
 * and store them in voucher metadata for audit and display purposes.
 */
abstract class AbstractDataEnricher
{
    /**
     * Extract rich data from gateway API response.
     *
     * @param array &$metadata Voucher metadata (by reference)
     * @param array $raw Raw gateway response
     * @return void
     */
    abstract public function extract(array &$metadata, array $raw): void;
    
    /**
     * Check if this enricher supports the given gateway.
     *
     * @param string $gateway Gateway name (e.g., 'netbank', 'bdo', 'gcash')
     * @return bool
     */
    abstract public function supports(string $gateway): bool;
}
