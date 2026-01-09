<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Input Field Mapper
 * 
 * Centralizes field name mapping between form-flow handlers and voucher expectations.
 * This ensures consistent field name transformations across all redemption flows.
 * 
 * **Why this exists:**
 * - Form flow handlers (especially published packages) use their own field conventions
 * - Vouchers may be configured with different field name expectations
 * - Rather than modifying published packages, we adapt at the application boundary
 * 
 * **Relationship with YAML Driver:**
 * The voucher-redemption.yaml driver ALSO performs transformations during auto-population:
 * - KYC handler returns: full_name, date_of_birth, address
 * - YAML bio step outputs: full_name, birth_date, address (transforms date_of_birth → birth_date)
 * - This mapper provides: Fallback + API flow consistency
 * 
 * **Mapping Rules:**
 * - KYC handler returns: full_name, date_of_birth
 * - Vouchers typically expect: name, birth_date
 * - OTP handler returns: otp_code
 * - Vouchers expect: otp
 */
class InputFieldMapper
{
    /**
     * Field name mappings (source => target)
     */
    protected array $mappings = [
        // KYC field mappings
        'full_name' => 'name',
        'date_of_birth' => 'birth_date',
        
        // OTP field mappings
        'otp_code' => 'otp',
    ];
    
    /**
     * Map input field names to voucher expectations
     * 
     * @param array $inputs Raw inputs from form flow or API
     * @return array Mapped inputs with standardized field names
     */
    public function map(array $inputs): array
    {
        $mapped = $inputs;
        
        foreach ($this->mappings as $source => $target) {
            if (isset($mapped[$source])) {
                $mapped[$target] = $mapped[$source];
                unset($mapped[$source]);
            }
        }
        
        return $mapped;
    }
    
    /**
     * Add a custom mapping at runtime
     * 
     * @param string $source Source field name
     * @param string $target Target field name
     * @return self
     */
    public function addMapping(string $source, string $target): self
    {
        $this->mappings[$source] = $target;
        return $this;
    }
    
    /**
     * Get all configured mappings
     * 
     * @return array
     */
    public function getMappings(): array
    {
        return $this->mappings;
    }
}
