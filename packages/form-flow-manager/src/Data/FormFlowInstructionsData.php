<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\DataCollectionOf;

/**
 * Form Flow Instructions Data
 * 
 * Generic instructions for a multi-step form flow.
 * Domain-agnostic - can be used for ANY multi-step input collection.
 */
class FormFlowInstructionsData extends Data
{
    public function __construct(
        /** Unique reference identifier (like HyperVerge transactionId) */
        public string $reference_id,
        
        /** Unique flow identifier (internal) */
        public ?string $flow_id = null,
        
        /** Array of steps to collect */
        #[DataCollectionOf(FormFlowStepData::class)]
        public array $steps = [],
        
        /** Callback URLs */
        public array $callbacks = [],
        
        /** Additional metadata */
        public array $metadata = [],
        
        /** Flow title (for display) */
        public ?string $title = null,
        
        /** Flow description (for display) */
        public ?string $description = null,
    ) {}
    
    /**
     * Get callback URL for event
     */
    public function getCallback(string $event): ?string
    {
        return $this->callbacks[$event] ?? null;
    }
    
    /**
     * Get steps sorted by priority
     */
    public function getSortedSteps(): array
    {
        $steps = $this->steps;
        usort($steps, fn($a, $b) => $a->priority <=> $b->priority);
        return $steps;
    }
    
    /**
     * Get required steps
     */
    public function getRequiredSteps(): array
    {
        return array_filter($this->steps, fn($step) => $step->required);
    }
    
    /**
     * Get optional steps
     */
    public function getOptionalSteps(): array
    {
        return array_filter($this->steps, fn($step) => !$step->required);
    }
    
    /**
     * Check if flow has a specific handler
     */
    public function hasHandler(string $handler): bool
    {
        foreach ($this->steps as $step) {
            if ($step->handler === $handler) {
                return true;
            }
        }
        return false;
    }
}
