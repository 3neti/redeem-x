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
        
        // Handle timeout - use default if empty/null/not set
        $timeout = $step->config['timeout'] ?? null;
        if (empty($timeout) && $timeout !== 0) {
            $timeout = config('splash.default_timeout', 5);
        } else {
            $timeout = (int) $timeout;
        }
        
        $title = $step->config['title'] ?? null;
        
        // Extract voucher code from config or context
        $voucherCode = $step->config['voucher_code'] ?? $context['voucher_code'] ?? $context['code'] ?? null;
        
        // Build context for default content generation
        $contentContext = array_merge($context, [
            'voucher_code' => $voucherCode,
            'code' => $voucherCode,
        ]);
        
        // Use default content if none provided
        if (empty($content)) {
            $content = $this->getDefaultContent($contentContext);
        }
        
        return Inertia::render('form-flow/core/Splash', [
            'flow_id' => $context['flow_id'] ?? null,
            'step_index' => $context['step_index'] ?? 0,
            'title' => $title,
            'content' => $content,
            'timeout' => $timeout,
            'button_label' => config('splash.button_label', 'Continue Now'),
        ]);
    }
    
    /**
     * Generate default splash content with app metadata
     */
    protected function getDefaultContent(array $context): string
    {
        // Check for custom default content in config
        $customContent = config('splash.default_content');
        if ($customContent) {
            return $this->replaceVariables($customContent, $context);
        }
        
        // Generate beautiful default splash screen
        $appName = config('app.name', 'Laravel');
        $appVersion = $this->getAppVersion();
        $author = config('splash.app_author', '3neti R&D OPC');
        $copyrightYear = config('splash.copyright_year', date('Y'));
        $copyrightHolder = config('splash.copyright_holder', '3neti R&D OPC');
        $voucherCode = $context['voucher_code'] ?? $context['code'] ?? null;
        
        $html = '<div class="text-center space-y-6 py-8">';
        
        // App logo/icon (using emoji as placeholder)
        $html .= '<div class="text-6xl mb-4">🎟️</div>';
        
        // App name
        $html .= '<h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">' . htmlspecialchars($appName) . '</h1>';
        
        // Version badge
        if ($appVersion) {
            $html .= '<div class="inline-block px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full text-sm font-medium mb-4">';
            $html .= 'v' . htmlspecialchars($appVersion);
            $html .= '</div>';
        }
        
        // Redemption message if voucher code available
        if ($voucherCode) {
            $html .= '<div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">';
            $html .= '<p class="text-lg text-blue-900 dark:text-blue-100 font-medium">Redeeming voucher</p>';
            $html .= '<p class="text-2xl font-mono font-bold text-blue-600 dark:text-blue-400 mt-1">' . htmlspecialchars($voucherCode) . '</p>';
            $html .= '</div>';
        } else {
            $html .= '<p class="text-lg text-gray-600 dark:text-gray-400 mt-4">Secure Voucher Redemption Platform</p>';
        }
        
        // Divider
        $html .= '<div class="my-6"><hr class="border-gray-200 dark:border-gray-700" /></div>';
        
        // Author
        $html .= '<p class="text-sm text-gray-500 dark:text-gray-500">Developed by<br /><strong class="text-gray-700 dark:text-gray-300">' . htmlspecialchars($author) . '</strong></p>';
        
        // Copyright
        $html .= '<p class="text-xs text-gray-400 dark:text-gray-600 mt-4">';
        $html .= '&copy; ' . htmlspecialchars($copyrightYear) . ' ' . htmlspecialchars($copyrightHolder) . '. All rights reserved.';
        $html .= '</p>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get app version from composer.json
     */
    protected function getAppVersion(): ?string
    {
        $composerPath = base_path('composer.json');
        
        if (!file_exists($composerPath)) {
            return null;
        }
        
        $composer = json_decode(file_get_contents($composerPath), true);
        
        return $composer['version'] ?? null;
    }
    
    /**
     * Replace template variables in content
     */
    protected function replaceVariables(string $content, array $context): string
    {
        $variables = [
            '{app_name}' => config('app.name', 'Laravel'),
            '{app_version}' => $this->getAppVersion() ?? '',
            '{app_author}' => config('splash.app_author', ''),
            '{copyright_year}' => config('splash.copyright_year', date('Y')),
            '{copyright_holder}' => config('splash.copyright_holder', ''),
            '{voucher_code}' => $context['voucher_code'] ?? $context['code'] ?? '',
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $content);
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
