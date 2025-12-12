<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerSignature;

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerSignature\Data\SignatureData;

/**
 * Signature Handler
 * 
 * Captures user's signature using HTML5 canvas drawing.
 * Stores image as base64-encoded string.
 */
class SignatureHandler implements FormHandlerInterface
{
    public function getName(): string
    {
        return 'signature';
    }
    
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        // Extract data from 'data' key if present (from form submission)
        $inputData = $request->input('data', $request->all());
        
        // Validate using Laravel's validator directly
        $validated = validator($inputData, [
            'image' => 'required|string', // base64 encoded image
            'width' => 'nullable|integer|min:100|max:2000',
            'height' => 'nullable|integer|min:50|max:1000',
            'format' => 'nullable|string|in:image/png,image/jpeg,image/webp',
        ])->validate();
        
        $validated['timestamp'] = now()->toIso8601String();
        $validated['width'] = $validated['width'] ?? config('signature-handler.width', 600);
        $validated['height'] = $validated['height'] ?? config('signature-handler.height', 256);
        $validated['format'] = $validated['format'] ?? config('signature-handler.format', 'image/png');
        
        return SignatureData::from($validated)->toArray();
    }
    
    public function validate(array $data, array $rules): bool
    {
        // Validation handled in handle() method
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        // Renders page at resources/js/pages/form-flow/signature/SignatureCapturePage.vue
        
        return Inertia::render('form-flow/signature/SignatureCapturePage', [
            'flow_id' => $context['flow_id'] ?? null,
            'step' => (string) ($context['step_index'] ?? 0),
            'config' => array_merge([
                'width' => config('signature-handler.width', 600),
                'height' => config('signature-handler.height', 256),
                'quality' => config('signature-handler.quality', 0.85),
                'format' => config('signature-handler.format', 'image/png'),
                'line_width' => config('signature-handler.line_width', 2),
                'line_color' => config('signature-handler.line_color', '#000000'),
                'line_cap' => config('signature-handler.line_cap', 'round'),
                'line_join' => config('signature-handler.line_join', 'round'),
            ], $step->config),
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'width' => 'nullable|integer|min:100|max:2000',
            'height' => 'nullable|integer|min:50|max:1000',
            'quality' => 'nullable|numeric|min:0|max:1',
            'format' => 'nullable|in:image/png,image/jpeg,image/webp',
            'line_width' => 'nullable|integer|min:1|max:10',
            'line_color' => 'nullable|string',
            'line_cap' => 'nullable|in:butt,round,square',
            'line_join' => 'nullable|in:bevel,round,miter',
        ];
    }
}
