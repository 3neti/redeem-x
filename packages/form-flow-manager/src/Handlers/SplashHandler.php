<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Handlers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

/**
 * Splash Handler
 * 
 * Displays a splash page with configurable content (text, markdown, HTML, SVG, or URL)
 * and an optional countdown timer before proceeding to the next step.
 * 
 * Content types auto-detected:
 * - Markdown (if contains # headers or ** bold)
 * - HTML (if contains <tags>)
 * - SVG (if starts with <svg)
 * - URL (if starts with http:// or https://)
 * - Plain text (fallback)
 */
class SplashHandler implements FormHandlerInterface
{
    public function getName(): string
    {
        return 'splash';
    }
    
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        // Splash doesn't collect data - just acknowledge the user has seen it
        return [
            'splash_viewed' => true,
            'viewed_at' => now()->toIso8601String(),
        ];
    }
    
    public function validate(array $data, array $rules): bool
    {
        // No validation needed for splash
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        $content = $step->config['content'] ?? '';
        $timeout = $step->config['timeout'] ?? 5;
        $title = $step->config['title'] ?? null;
        
        return Inertia::render('form-flow/core/Splash', [
            'flow_id' => $context['flow_id'] ?? null,
            'step_index' => $context['step_index'] ?? 0,
            'title' => $title,
            'content' => $content,
            'timeout' => $timeout,
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'title' => 'nullable|string',
            'content' => 'required|string|max:51200', // 50KB max
            'timeout' => 'nullable|integer|min:0|max:60', // 0-60 seconds
        ];
    }
}
