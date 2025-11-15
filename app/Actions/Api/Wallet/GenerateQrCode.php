<?php

declare(strict_types=1);

namespace App\Actions\Api\Wallet;

use Brick\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

/**
 * Generate QR Code for Wallet Loading
 * 
 * Generates a QR Ph code via Omnipay gateway that can be shared
 * for others to scan and load funds into the user's wallet.
 */
class GenerateQrCode
{
    public function __construct(
        private PaymentGatewayInterface $gateway
    ) {}
    
    /**
     * Generate QR code.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'force' => ['nullable', 'boolean'],
        ]);
        
        $user = $request->user();
        $account = $user->email; // Use email as account identifier
        $amountValue = (float) $request->input('amount', 0);
        $force = (bool) $request->input('force', false);
        $currency = config('disbursement.currency', 'PHP');
        
        // Get or create merchant profile for user
        $merchant = $user->getOrCreateMerchant();
        
        // Use merchant default amount if no amount specified AND merchant is not dynamic
        if ($amountValue === 0.0 && !$merchant->is_dynamic && $merchant->default_amount) {
            $amountValue = (float) $merchant->default_amount;
        }
        
        // Create cache key based on user and amount
        $cacheKey = "qr_code:{$user->id}:" . ($amountValue > 0 ? $amountValue : 'dynamic');
        $cacheTtl = config('payment-gateway.qr_cache_ttl', 3600); // Default 1 hour
        
        try {
            // If force regenerate, clear cache first
            if ($force) {
                Cache::forget($cacheKey);
                Log::info('[GenerateQrCode] Cache cleared due to force regenerate', [
                    'cache_key' => $cacheKey,
                ]);
            }
            
            // Check cache first (unless forced)
            $cachedData = !$force ? Cache::get($cacheKey) : null;
            
            if ($cachedData) {
                Log::info('[GenerateQrCode] Returning cached QR code', [
                    'user_id' => $user->id,
                    'account' => $account,
                    'amount' => $amountValue,
                    'cache_key' => $cacheKey,
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $cachedData,
                    'message' => 'QR code generated successfully',
                    'cached' => true,
                ]);
            }
            
            Log::info('[GenerateQrCode] Generating new QR code from gateway', [
                'user_id' => $user->id,
                'account' => $account,
                'amount' => $amountValue,
            ]);
            
            // Create Brick\Money\Money object
            $money = Money::of($amountValue, $currency);
            
            // Prepare merchant data for QR (pass full objects for template rendering)
            $merchantData = [
                'merchant' => $merchant,
                'user' => $user,
            ];
            
            // Generate QR code via gateway with merchant info
            $qrCode = $this->gateway->generate($account, $money, $merchantData);
            
            // Build shareable URL with merchant UUID for public access
            $shareableUrl = route('load.public', ['uuid' => $merchant->uuid]);
            
            // Render display name using merchant's template (fallback to config or default)
            $templateService = app(\App\Services\MerchantNameTemplateService::class);
            $template = $merchant->merchant_name_template 
                ?? config('payment-gateway.qr_merchant_name.template', '{name} - {city}');
            $displayName = $templateService->render($template, $merchant, $user);
            
            Log::info('[GenerateQrCode] QR code generated successfully', [
                'user_id' => $user->id,
                'account' => $account,
                'cache_ttl' => $cacheTtl,
                'display_name' => $displayName,
            ]);
            
            // Prepare response data
            $responseData = [
                'qr_code' => $this->ensureDataUrl($qrCode),
                'qr_url' => null, // NetBank gateway might not return URL
                'qr_id' => 'QR-' . strtoupper(uniqid()),
                'expires_at' => null, // Add if gateway provides expiration
                'account' => $account,
                'amount' => $amountValue > 0 ? $amountValue : null,
                'shareable_url' => $shareableUrl,
                'merchant' => [
                    'name' => $merchant->name,
                    'city' => $merchant->city,
                    'description' => $merchant->description,
                    'category' => $merchant->category_name,
                    'display_name' => $displayName, // Rendered display name for UI
                ],
            ];
            
            // Cache the QR code data
            Cache::put($cacheKey, $responseData, $cacheTtl);
            
            Log::debug('[GenerateQrCode] QR code cached', [
                'cache_key' => $cacheKey,
                'ttl_seconds' => $cacheTtl,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $responseData,
                'message' => 'QR code generated successfully',
                'cached' => false,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('[GenerateQrCode] Failed to generate QR code', [
                'user_id' => $user->id,
                'account' => $account,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Ensure QR code is a data URL.
     *
     * @param string $qrCode
     * @return string
     */
    private function ensureDataUrl(string $qrCode): string
    {
        // If already a data URL, return as is
        if (str_starts_with($qrCode, 'data:')) {
            return $qrCode;
        }
        
        // Otherwise, assume it's base64 and add the data URL prefix
        return 'data:image/png;base64,' . $qrCode;
    }
}
