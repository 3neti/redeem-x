<?php

namespace App\Services;

class SuccessContentService
{
    /**
     * Process content and detect its type
     */
    public function processContent(?string $content, array $context): ?array
    {
        if (empty($content)) {
            return null;
        }
        
        // Replace variables first
        $processed = $this->replaceVariables($content, $context);
        
        // Detect content type
        $type = $this->detectContentType($processed);
        
        return [
            'type' => $type,
            'content' => $processed,
            'raw' => $content,
        ];
    }
    
    /**
     * Replace template variables in content
     */
    public function replaceVariables(string $content, array $context): string
    {
        $variables = [
            '{app_name}' => config('app.name', 'Laravel'),
            '{voucher_code}' => $context['voucher_code'] ?? '',
            '{amount}' => $context['amount'] ?? '',
            '{mobile}' => $context['mobile'] ?? '',
            '{currency}' => $context['currency'] ?? 'PHP',
            '{app_author}' => config('splash.app_author', ''),
            '{copyright_year}' => date('Y'),
            '{copyright_holder}' => config('splash.copyright_holder', ''),
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $content);
    }
    
    /**
     * Detect content type based on content
     */
    protected function detectContentType(string $content): string
    {
        $trimmed = trim($content);
        
        // SVG detection
        if (str_starts_with($trimmed, '<svg')) {
            return 'svg';
        }
        
        // HTML detection
        if (preg_match('/<[a-z][\s\S]*>/i', $trimmed)) {
            return 'html';
        }
        
        // URL detection
        if (preg_match('/^https?:\/\//i', $trimmed)) {
            return 'url';
        }
        
        // Markdown detection (has # headers or ** bold or * list)
        if (preg_match('/^#+\s|^\*\*|\*\s/m', $trimmed)) {
            return 'markdown';
        }
        
        // Fallback to plain text
        return 'text';
    }
}
