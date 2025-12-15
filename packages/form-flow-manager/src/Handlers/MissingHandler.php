<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Handlers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

/**
 * Missing Handler
 * 
 * Fallback handler for when a required handler is not installed.
 * Shows environment-aware message (error in production, skip in development).
 */
class MissingHandler implements FormHandlerInterface
{
    public function getName(): string
    {
        return 'missing';
    }
    
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        $stepName = $step->config['step_name'] ?? null;
        
        // In non-production, allow skipping the step
        if (!app()->environment('production')) {
            $data = [
                '_handler_missing' => true,
                '_handler_name' => $step->config['missing_handler_name'] ?? 'unknown',
                '_skip_reason' => 'Handler not installed',
                '_skipped_at' => now()->toIso8601String(),
            ];
            
            // Add step_name if present (for named references)
            if ($stepName) {
                $data['_step_name'] = $stepName;
            }
            
            return $data;
        }
        
        // In production, return metadata but block completion
        // (the Complete page should check for _handler_missing and block)
        $data = [
            '_handler_missing' => true,
            '_handler_name' => $step->config['missing_handler_name'] ?? 'unknown',
            '_error' => 'Required handler not available',
            '_blocked_at' => now()->toIso8601String(),
        ];
        
        if ($stepName) {
            $data['_step_name'] = $stepName;
        }
        
        return $data;
    }
    
    public function validate(array $data, array $rules): bool
    {
        // No validation needed for fallback
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        $handlerName = $step->config['missing_handler_name'] ?? 'unknown';
        $handlerTitle = $step->config['missing_handler_title'] ?? 'Unknown Step';
        $installHint = $step->config['install_hint'] ?? "composer require lbhurtado/form-handler-{$handlerName}";
        $isProduction = app()->environment('production');
        
        // Log the missing handler
        \Log::warning('[FormFlow] Missing handler detected', [
            'handler' => $handlerName,
            'flow_id' => $context['flow_id'] ?? null,
            'environment' => app()->environment(),
        ]);
        
        return Inertia::render('form-flow/core/MissingHandler', [
            'flow_id' => $context['flow_id'] ?? null,
            'step_index' => $context['step_index'] ?? 0,
            'handler_name' => $handlerName,
            'handler_title' => $handlerTitle,
            'install_hint' => $installHint,
            'is_production' => $isProduction,
            'can_skip' => !$isProduction,
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'missing_handler_name' => 'required|string',
            'missing_handler_title' => 'nullable|string',
            'install_hint' => 'nullable|string',
        ];
    }
}
