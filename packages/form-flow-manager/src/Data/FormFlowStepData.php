<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Data;

use Spatie\LaravelData\Data;

/**
 * Form Flow Step Data
 * 
 * Represents a single step in a multi-step form flow.
 * Generic and handler-agnostic - the handler name determines behavior.
 */
class FormFlowStepData extends Data
{
    public function __construct(
        /** Handler identifier (e.g., 'location', 'selfie', 'kyc') */
        public string $handler,
        
        /** Handler-specific configuration */
        public array $config = [],
        
        /** Optional validation rules for this step */
        public ?array $validation_rules = null,
        
        /** Display priority (lower = earlier in flow) */
        public int $priority = 100,
        
        /** Whether this step is required */
        public bool $required = true,
        
        /** Conditional display logic */
        public ?string $show_if = null,
    ) {}
    
    /**
     * Check if step should be shown based on context
     */
    public function shouldShow(array $context = []): bool
    {
        if ($this->show_if === null) {
            return true;
        }
        
        // Will be evaluated by ExpressionEvaluator in practice
        return true;
    }
    
    /**
     * Check if step is required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }
}
