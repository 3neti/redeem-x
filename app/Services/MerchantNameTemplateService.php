<?php

declare(strict_types=1);

namespace App\Services;

use LBHurtado\PaymentGateway\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service for rendering merchant name templates for QR code display.
 * 
 * Supports variable substitution and formatting options.
 */
class MerchantNameTemplateService
{
    /**
     * Render a merchant name template with variable substitution.
     * 
     * @param string $template Template string with {variable} placeholders
     * @param Merchant $merchant Merchant model
     * @param User $user User model
     * @return string Rendered merchant name
     */
    public function render(string $template, Merchant $merchant, User $user): string
    {
        // Build variable map
        $variables = [
            '{name}' => $merchant->name ?? '',
            '{city}' => $merchant->city ?? '',
            '{app_name}' => config('app.name', 'App'),
        ];
        
        // Replace variables in template
        $result = str_replace(
            array_keys($variables),
            array_values($variables),
            $template
        );
        
        // Clean up extra whitespace and separators
        $result = $this->cleanupResult($result);
        
        // Apply uppercase transformation if configured
        if (config('payment-gateway.qr_merchant_name.uppercase', false)) {
            $result = mb_strtoupper($result, 'UTF-8');
        }
        
        // Apply fallback if result is empty
        if (empty(trim($result))) {
            $fallback = config('payment-gateway.qr_merchant_name.fallback', config('app.name'));
            
            Log::warning('[MerchantNameTemplate] Template resulted in empty string, using fallback', [
                'template' => $template,
                'fallback' => $fallback,
                'merchant_id' => $merchant->id,
            ]);
            
            $result = $fallback;
        }
        
        return trim($result);
    }
    
    /**
     * Clean up the rendered result by removing orphaned separators and extra whitespace.
     * 
     * @param string $result Raw rendered string
     * @return string Cleaned string
     */
    protected function cleanupResult(string $result): string
    {
        // Remove orphaned separators at start/end
        $result = preg_replace('/^\s*[•\-|,]\s*/', '', $result);
        $result = preg_replace('/\s*[•\-|,]\s*$/', '', $result);
        
        // Replace multiple spaces with single space
        $result = preg_replace('/\s+/', ' ', $result);
        
        // Remove separator with only whitespace around it (e.g., " • " when one side is empty)
        $result = preg_replace('/\s*[•\-|,]\s*[•\-|,]\s*/', ' ', $result);
        
        return $result;
    }
    
    /**
     * Get available template variables.
     * 
     * @return array<string, string> Map of variable names to descriptions
     */
    public static function getAvailableVariables(): array
    {
        return [
            '{name}' => 'Merchant name from profile',
            '{city}' => 'Merchant city from profile',
            '{app_name}' => 'Application name',
        ];
    }
}
