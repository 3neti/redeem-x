<?php

namespace App\Services\DataEnrichers;

/**
 * Registry for gateway-specific data enrichers.
 * 
 * Automatically selects the correct enricher based on gateway name.
 * Falls back to DefaultDataEnricher for unknown gateways.
 */
class DataEnricherRegistry
{
    /**
     * @var AbstractDataEnricher[]
     */
    protected array $enrichers = [];
    
    public function __construct()
    {
        // Auto-register enrichers
        // Gateway-specific enrichers should be registered first
        $this->register(new NetBankDataEnricher());
        
        // Default enricher must be registered last (fallback)
        $this->register(new DefaultDataEnricher());
    }
    
    /**
     * Register a data enricher.
     *
     * @param AbstractDataEnricher $enricher
     * @return void
     */
    public function register(AbstractDataEnricher $enricher): void
    {
        $this->enrichers[] = $enricher;
    }
    
    /**
     * Get the appropriate enricher for a gateway.
     * 
     * Loops through registered enrichers and returns the first one
     * that supports the given gateway name.
     *
     * @param string $gateway Gateway name (e.g., 'netbank', 'bdo', 'gcash')
     * @return AbstractDataEnricher
     */
    public function getEnricher(string $gateway): AbstractDataEnricher
    {
        foreach ($this->enrichers as $enricher) {
            if ($enricher->supports($gateway)) {
                return $enricher;
            }
        }
        
        // Should never reach here if DefaultDataEnricher is registered
        // But just in case, return a new DefaultDataEnricher instance
        return new DefaultDataEnricher();
    }
}
