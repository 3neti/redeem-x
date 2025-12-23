<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        
        // Check if there's a completed KYC step in cache (from callback)
        // This handles async handlers where the callback runs in a different session
        $kycCompleted = Cache::get("kyc_completed.{$flowId}");
        if ($kycCompleted) {
            // Apply the completed step to current session
            $this->flowService->updateStepData(
                $flowId,
                $kycCompleted['step_index'],
                $kycCompleted['kyc_data']
            );
            
            // Clear cache entry
            Cache::forget("kyc_completed.{$flowId}");
            
            // Reload state after update
            $state = $this->flowService->getFlowState($flowId);
            
            Log::debug('[FormFlowController] Applied completed step from cache', [
                'flow_id' => $flowId,
                'step_index' => $kycCompleted['step_index'],
            ]);
        }
        
        // If JSON is requested, return state
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'state' => $state,
            ]);
        }
        
        // Check if all steps are completed
        $currentStepIndex = $state['current_step'];
        $totalSteps = count($state['instructions']['steps']);
        
        if ($currentStepIndex >= $totalSteps) {
            // All steps completed - automatically complete the flow
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
            
            // Render a completion page or redirect
            return inertia('form-flow/core/Complete', [
                'flow_id' => $flowId,
                'state' => $state,
                'callback_triggered' => $callbackUrl !== null,
            ]);
        }
        
        // Otherwise, render the current step's handler view
        $stepData = FormFlowStepData::from($state['instructions']['steps'][$currentStepIndex]);
        
        // Get the handler by name
        $handlerClass = $this->getHandlerClass($stepData->handler);
        
        if (!$handlerClass) {
            // Fallback to MissingHandler instead of crashing
            $handlerClass = \LBHurtado\FormFlowManager\Handlers\MissingHandler::class;
            \Log::warning('[FormFlow] Missing handler at runtime', [
                'handler' => $stepData->handler,
                'flow_id' => $flowId,
                'step_index' => $currentStepIndex,
            ]);
        }
        
        $handler = app($handlerClass);
        
        // Render the handler's view
        return $handler->render($stepData, [
            'flow_id' => $flowId,
            'step_index' => $currentStepIndex,
            'collected_data' => $state['collected_data'] ?? [],
        ]);
    }
    
    /**
     * Update step data
     * 
     * POST /form-flow/{flow_id}/step/{step}
     */
    public function updateStep(Request $request, string $flowId, int $step)
    {
        // First, validate that data is present
        $request->validate([
            'data' => 'required|array',
        ]);
        
        try {
            // Get flow state to access step config
            $state = $this->flowService->getFlowState($flowId);
            
            if (!$state) {
                throw new \RuntimeException('Flow not found');
            }
            
            // Get the step data
            $stepData = FormFlowStepData::from($state['instructions']['steps'][$step]);
            
            // Get the handler
            $handlerClass = $this->getHandlerClass($stepData->handler);
            
            if (!$handlerClass) {
                // Fallback to MissingHandler instead of throwing
                $handlerClass = \LBHurtado\FormFlowManager\Handlers\MissingHandler::class;
                \Log::warning('[FormFlow] Missing handler at runtime (updateStep)', [
                    'handler' => $stepData->handler,
                    'flow_id' => $flowId,
                    'step_index' => $step,
                ]);
            }
            
            $handler = app($handlerClass);
            
            // Process data through handler (validates and transforms)
            // Handlers should extract data from request->input('data') or request->all()
            $processedData = $handler->handle($request, $stepData, [
                'flow_id' => $flowId,
                'step_index' => $step,
                'collected_data' => $state['collected_data'] ?? [],
            ]);
            
            // Inject step_name if present in config
            $stepName = $stepData->config['step_name'] ?? null;
            
            // Update the flow state with processed data
            $state = $this->flowService->updateStepData($flowId, $step, $processedData, $stepName);
            
            // Check if this is an Inertia request (has X-Inertia header)
            $isInertia = $request->header('X-Inertia');
            
            // For Inertia requests, redirect to next step
            if ($isInertia) {
                $totalSteps = count($state['instructions']['steps']);
                $nextStepIndex = $state['current_step'];
                
                if ($nextStepIndex < $totalSteps) {
                    // Redirect to show next step (Inertia will render it)
                    return redirect()->route('form-flow.show', ['flow_id' => $flowId]);
                }
                
                // All steps completed - could redirect to completion page
                return redirect()->route('form-flow.show', ['flow_id' => $flowId]);
            }
            
            // For API/test requests, return JSON
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
     * Built-in handler: 'form' (for basic inputs)
     * Plugin handlers registered via config: 'location', 'selfie', etc.
     * 
     * @param string $handlerName Handler name
     * @return string|null Handler class name or null if not found
     */
    protected function getHandlerClass(string $handlerName): ?string
    {
        // Get handlers from config (allows plugins to register)
        $configHandlers = config('form-flow.handlers', []);
        
        // Built-in handlers
        $builtInHandlers = [
            'form' => \LBHurtado\FormFlowManager\Handlers\FormHandler::class,
            'splash' => \LBHurtado\FormFlowManager\Handlers\SplashHandler::class,
        ];
        
        // Merge: config handlers override built-in if needed
        $handlers = array_merge($builtInHandlers, $configHandlers);
        
        return $handlers[$handlerName] ?? null;
    }
}
