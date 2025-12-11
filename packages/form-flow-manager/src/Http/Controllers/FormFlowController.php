<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\FormFlowService;

/**
 * Form Flow Controller
 * 
 * Handles HTTP requests for form flow management.
 * Routes: /form-flow/*
 */
class FormFlowController extends Controller
{
    public function __construct(
        protected FormFlowService $flowService
    ) {}
    
    /**
     * Start a new form flow
     * 
     * POST /form-flow/start
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'flow_id' => 'required|string',
            'steps' => 'required|array',
            'steps.*.handler' => 'required|string',
            'steps.*.config' => 'array',
            'callbacks' => 'array',
            'metadata' => 'array',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        
        $instructions = FormFlowInstructionsData::from($validated);
        $state = $this->flowService->startFlow($instructions);
        
        return response()->json([
            'success' => true,
            'flow_id' => $state['flow_id'],
            'state' => $state,
            'next_url' => route('form-flow.show', ['flow_id' => $state['flow_id']]),
        ]);
    }
    
    /**
     * Get flow state
     * 
     * GET /form-flow/{flow_id}
     */
    public function show(string $flowId)
    {
        $state = $this->flowService->getFlowState($flowId);
        
        if (!$state) {
            abort(404, 'Flow not found');
        }
        
        return response()->json([
            'success' => true,
            'state' => $state,
        ]);
    }
    
    /**
     * Update step data
     * 
     * POST /form-flow/{flow_id}/step/{step}
     */
    public function updateStep(Request $request, string $flowId, int $step)
    {
        $data = $request->validate([
            'data' => 'required|array',
        ]);
        
        try {
            $state = $this->flowService->updateStepData($flowId, $step, $data['data']);
            
            return response()->json([
                'success' => true,
                'state' => $state,
                'next_step' => $state['current_step'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
    
    /**
     * Complete flow and trigger callback
     * 
     * POST /form-flow/{flow_id}/complete
     */
    public function complete(Request $request, string $flowId)
    {
        try {
            $state = $this->flowService->completeFlow($flowId);
            
            // Trigger on_complete callback if defined
            $callbackUrl = $state['instructions']['callbacks']['on_complete'] ?? null;
            
            if ($callbackUrl) {
                $this->triggerCallback($callbackUrl, [
                    'flow_id' => $flowId,
                    'status' => 'completed',
                    'collected_data' => $state['collected_data'],
                    'completed_at' => $state['completed_at'],
                ]);
            }
            
            return response()->json([
                'success' => true,
                'state' => $state,
                'callback_triggered' => $callbackUrl !== null,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
    
    /**
     * Cancel flow and trigger callback
     * 
     * POST /form-flow/{flow_id}/cancel
     */
    public function cancel(Request $request, string $flowId)
    {
        try {
            $state = $this->flowService->cancelFlow($flowId);
            
            // Trigger on_cancel callback if defined
            $callbackUrl = $state['instructions']['callbacks']['on_cancel'] ?? null;
            
            if ($callbackUrl) {
                $this->triggerCallback($callbackUrl, [
                    'flow_id' => $flowId,
                    'status' => 'cancelled',
                    'collected_data' => $state['collected_data'],
                    'cancelled_at' => $state['cancelled_at'],
                ]);
            }
            
            return response()->json([
                'success' => true,
                'state' => $state,
                'callback_triggered' => $callbackUrl !== null,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
    
    /**
     * Clear flow state
     * 
     * DELETE /form-flow/{flow_id}
     */
    public function destroy(string $flowId)
    {
        $this->flowService->clearFlow($flowId);
        
        return response()->json([
            'success' => true,
            'message' => 'Flow cleared',
        ]);
    }
    
    /**
     * Trigger callback URL
     * 
     * @param string $url Callback URL
     * @param array $data Payload data
     * @return void
     */
    protected function triggerCallback(string $url, array $data): void
    {
        try {
            Http::post($url, $data);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            logger()->error("Failed to trigger callback: {$url}", [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }
}
