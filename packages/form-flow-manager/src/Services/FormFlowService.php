<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Services;

use Illuminate\Support\Facades\Session;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;

/**
 * Form Flow Service
 * 
 * Manages form flow state using session storage.
 * Isolated session keys: form_flow.{flow_id}.*
 */
class FormFlowService
{
    /**
     * Start a new form flow
     * 
     * @param FormFlowInstructionsData $instructions Flow instructions
     * @return array Flow state
     */
    public function startFlow(FormFlowInstructionsData $instructions): array
    {
        $state = [
            'flow_id' => $instructions->flow_id,
            'instructions' => $instructions->toArray(),
            'current_step' => 0,
            'completed_steps' => [],
            'collected_data' => [],
            'started_at' => now()->toIso8601String(),
            'status' => 'active',
        ];
        
        $this->saveFlowState($instructions->flow_id, $state);
        
        return $state;
    }
    
    /**
     * Get flow state
     * 
     * @param string $flowId Flow identifier
     * @return array|null Flow state or null if not found
     */
    public function getFlowState(string $flowId): ?array
    {
        return Session::get($this->getSessionKey($flowId));
    }
    
    /**
     * Update step data
     * 
     * @param string $flowId Flow identifier
     * @param int $stepIndex Step index
     * @param array $data Step data
     * @return array Updated flow state
     */
    public function updateStepData(string $flowId, int $stepIndex, array $data): array
    {
        $state = $this->getFlowState($flowId);
        
        if (!$state) {
            throw new \RuntimeException("Flow not found: {$flowId}");
        }
        
        // Store step data
        $state['collected_data'][$stepIndex] = $data;
        
        // Mark step as completed
        if (!in_array($stepIndex, $state['completed_steps'])) {
            $state['completed_steps'][] = $stepIndex;
        }
        
        // Move to next step if not already there
        if ($state['current_step'] === $stepIndex) {
            $state['current_step'] = $stepIndex + 1;
        }
        
        $state['updated_at'] = now()->toIso8601String();
        
        $this->saveFlowState($flowId, $state);
        
        return $state;
    }
    
    /**
     * Complete flow
     * 
     * @param string $flowId Flow identifier
     * @return array Final flow state
     */
    public function completeFlow(string $flowId): array
    {
        $state = $this->getFlowState($flowId);
        
        if (!$state) {
            throw new \RuntimeException("Flow not found: {$flowId}");
        }
        
        $state['status'] = 'completed';
        $state['completed_at'] = now()->toIso8601String();
        
        $this->saveFlowState($flowId, $state);
        
        return $state;
    }
    
    /**
     * Cancel flow
     * 
     * @param string $flowId Flow identifier
     * @return array Final flow state
     */
    public function cancelFlow(string $flowId): array
    {
        $state = $this->getFlowState($flowId);
        
        if (!$state) {
            throw new \RuntimeException("Flow not found: {$flowId}");
        }
        
        $state['status'] = 'cancelled';
        $state['cancelled_at'] = now()->toIso8601String();
        
        $this->saveFlowState($flowId, $state);
        
        return $state;
    }
    
    /**
     * Clear flow state
     * 
     * @param string $flowId Flow identifier
     * @return void
     */
    public function clearFlow(string $flowId): void
    {
        Session::forget($this->getSessionKey($flowId));
    }
    
    /**
     * Check if flow exists
     * 
     * @param string $flowId Flow identifier
     * @return bool Whether flow exists
     */
    public function flowExists(string $flowId): bool
    {
        return Session::has($this->getSessionKey($flowId));
    }
    
    /**
     * Get current step index
     * 
     * @param string $flowId Flow identifier
     * @return int Current step index
     */
    public function getCurrentStep(string $flowId): int
    {
        $state = $this->getFlowState($flowId);
        return $state['current_step'] ?? 0;
    }
    
    /**
     * Get collected data
     * 
     * @param string $flowId Flow identifier
     * @return array Collected data
     */
    public function getCollectedData(string $flowId): array
    {
        $state = $this->getFlowState($flowId);
        return $state['collected_data'] ?? [];
    }
    
    /**
     * Check if flow is complete
     * 
     * @param string $flowId Flow identifier
     * @return bool Whether flow is complete
     */
    public function isComplete(string $flowId): bool
    {
        $state = $this->getFlowState($flowId);
        return $state && $state['status'] === 'completed';
    }
    
    /**
     * Get session key for flow
     * 
     * @param string $flowId Flow identifier
     * @return string Session key
     */
    protected function getSessionKey(string $flowId): string
    {
        return "form_flow.{$flowId}";
    }
    
    /**
     * Save flow state to session
     * 
     * @param string $flowId Flow identifier
     * @param array $state Flow state
     * @return void
     */
    protected function saveFlowState(string $flowId, array $state): void
    {
        Session::put($this->getSessionKey($flowId), $state);
    }
}
