<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerSelfie;

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerSelfie\Data\SelfieData;

/**
 * Selfie Handler
 * 
 * Captures user's selfie using browser camera (MediaDevices API).
 * Stores image as base64-encoded string.
 */
class SelfieHandler implements FormHandlerInterface
{
    public function getName(): string
    {
        return 'selfie';
    }
    
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        // Extract data from 'data' key if present (from form submission)
        $inputData = $request->input('data', $request->all());
        
        // Validate using Laravel's validator directly
        $validated = validator($inputData, [
            'image' => 'required|string', // base64 encoded image
            'width' => 'nullable|integer|min:320|max:1920',
            'height' => 'nullable|integer|min:240|max:1080',
            'format' => 'nullable|string|in:image/jpeg,image/png,image/webp',
        ])->validate();
        
        $validated['timestamp'] = now()->toIso8601String();
        $validated['width'] = $validated['width'] ?? config('selfie-handler.width', 640);
        $validated['height'] = $validated['height'] ?? config('selfie-handler.height', 480);
        $validated['format'] = $validated['format'] ?? config('selfie-handler.format', 'image/jpeg');
        
        return SelfieData::from($validated)->toArray();
    }
    
    public function validate(array $data, array $rules): bool
    {
        // Validation handled in handle() method
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        // Renders page at resources/js/pages/form-flow/selfie/SelfieCapturePage.vue
        
        return Inertia::render('form-flow/selfie/SelfieCapturePage', [
            'flow_id' => $context['flow_id'] ?? null,
            'step' => (string) ($context['step_index'] ?? 0),
            'config' => array_merge([
                'width' => config('selfie-handler.width', 640),
                'height' => config('selfie-handler.height', 480),
                'quality' => config('selfie-handler.quality', 0.85),
                'format' => config('selfie-handler.format', 'image/jpeg'),
                'facing_mode' => config('selfie-handler.facing_mode', 'user'),
                'show_guide' => config('selfie-handler.show_guide', true),
            ], $step->config),
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'width' => 'nullable|integer|min:320|max:1920',
            'height' => 'nullable|integer|min:240|max:1080',
            'quality' => 'nullable|numeric|min:0|max:1',
            'format' => 'nullable|in:image/jpeg,image/png,image/webp',
            'facing_mode' => 'nullable|in:user,environment',
            'show_guide' => 'nullable|boolean',
        ];
    }
}
