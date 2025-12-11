<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Data;

use Spatie\LaravelData\Data;

/**
 * Driver configuration schema (DirXML-style)
 * 
 * Represents a declarative mapping configuration for transforming
 * domain-specific data structures into generic form flow instructions.
 */
class DriverConfigData extends Data
{
    public function __construct(
        /** Driver name (e.g., 'voucher-redemption') */
        public string $name,
        
        /** Driver version (semantic versioning) */
        public string $version,
        
        /** Source class FQCN (e.g., 'LBHurtado\Voucher\Data\VoucherInstructionsData') */
        public string $source,
        
        /** Target class FQCN (e.g., 'LBHurtado\FormFlowManager\Data\FormFlowInstructionsData') */
        public string $target,
        
        /** 
         * Field mapping rules 
         * Structure:
         * [
         *   'field_name' => [
         *     'template' => 'voucher_{{ source.code }}',
         *     'source' => 'instructions.inputs.fields',
         *     'transform' => 'array_map',
         *     ...
         *   ],
         *   ...
         * ]
         */
        public array $mappings,
        
        /** 
         * Constant values accessible in templates
         * Structure:
         * [
         *   'priorities' => ['location' => 10, 'selfie' => 20, ...],
         *   ...
         * ]
         */
        public ?array $constants = null,
        
        /** 
         * Filtering rules
         * Structure:
         * [
         *   'steps' => ["config('form-flow.handlers.{{ item }}.enabled', true)"],
         *   'skip_flow_if' => ["empty(source.instructions.inputs.fields)"],
         * ]
         */
        public ?array $filters = null,
    ) {}
    
    /**
     * Get a constant value by key
     */
    public function getConstant(string $key, mixed $default = null): mixed
    {
        return data_get($this->constants, $key, $default);
    }
    
    /**
     * Check if driver has filters defined
     */
    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }
    
    /**
     * Get mapping for a specific field
     */
    public function getMappingForField(string $field): ?array
    {
        return $this->mappings[$field] ?? null;
    }
}
