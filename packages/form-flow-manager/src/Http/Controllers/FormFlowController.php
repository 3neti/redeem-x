<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;

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
     * 
     * Following HyperVerge pattern:
     * - Accepts reference_id (unique transaction identifier)
     * - Returns flow_url for separate session access
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'reference_id' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Check if reference_id already exists
                    $existingFlow = $this->flowService->getFlowStateByReference($value);
                    if ($existingFlow) {
                        $fail('The reference_id has already been used.');
                    }
                },
            ],
            'steps' => 'required|array|min:1',
            'steps.*.handler' => 'required|string',
            'steps.*.config' => 'nullable|array',
            'callbacks' => 'required|array',
            'callbacks.on_complete' => 'required|url',
            'callbacks.on_cancel' => 'nullable|url',
            'metadata' => 'nullable|array',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        
        $instructions = FormFlowInstructionsData::from($validated);
        $state = $this->flowService->startFlow($instructions);
        
        return response()->json([
            'success' => true,
            'reference_id' => $state['reference_id'],
            'flow_url' => route('form-flow.show', ['flow_id' => $state['flow_id']]),
        ]);
    }
    
    /**
     * Get flow state or render current step
     * 
     * GET /form-flow/{flow_id}
     */
    public function show(Request $request, string $flowId)
    {
        $state = $this->flowService->getFlowState($flowId);
        
        if (!$state) {
            abort(404, 'Flow not found');
        }
        
        // If JSON is requested, return state
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'state' => $state,
            ]);
        }
        
        // Otherwise, render the current step's handler view
        $currentStepIndex = $state['current_step'];
        $stepData = FormFlowStepData::from($state['instructions']['steps'][$currentStepIndex]);
        
        // Get the handler by name
        $handlerClass = $this->getHandlerClass($stepData->handler);
        
        if (!$handlerClass) {
            abort(500, "Handler not found: {$stepData->handler}");
        }
        
        $handler = app($handlerClass);
        
        // Render the handler's view
        return $handler->render($stepData, [
            'flow_id' => $flowId,
            'step_index' => $currentStepIndex,
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
    
    /**
     * Get handler class from handler name
     * 
     * Maps handler names to their class implementations.
     * For now, hardcoded. In future, use a registry.
     * 
     * @param string $handlerName Handler name (e.g., 'location', 'selfie')
     * @return string|null Handler class name or null if not found
     */
    protected function getHandlerClass(string $handlerName): ?string
    {
        $handlers = [
            'location' => \LBHurtado\FormHandlerLocation\LocationHandler::class,
            // Add more handlers here as they are created
            // 'selfie' => \LBHurtado\FormHandlerSelfie\SelfieHandler::class,
            // 'signature' => \LBHurtado\FormHandlerSignature\SignatureHandler::class,
            // 'kyc' => \LBHurtado\FormHandlerKyc\KycHandler::class,
        ];
        
        return $handlers[$handlerName] ?? null;
    }
}
