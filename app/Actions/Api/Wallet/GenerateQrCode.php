<?php

declare(strict_types=1);

namespace App\Actions\Api\Wallet;

use Brick\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ]);
        
        $user = $request->user();
        $account = $user->email; // Use email as account identifier
        $amountValue = (float) $request->input('amount', 0);
        $currency = config('disbursement.currency', 'PHP');
        
        try {
            Log::info('[GenerateQrCode] Generating QR code', [
                'user_id' => $user->id,
                'account' => $account,
                'amount' => $amountValue,
            ]);
            
            // Create Brick\Money\Money object
            $money = Money::of($amountValue, $currency);
            
            // Generate QR code via gateway
            $qrCode = $this->gateway->generate($account, $money);
            
            // Build shareable URL (you can customize this)
            $shareableUrl = route('wallet.load'); // For now, just link to the load page
            
            Log::info('[GenerateQrCode] QR code generated successfully', [
                'user_id' => $user->id,
                'account' => $account,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'qr_code' => $this->ensureDataUrl($qrCode),
                    'qr_url' => null, // NetBank gateway might not return URL
                    'qr_id' => 'QR-' . strtoupper(uniqid()),
                    'expires_at' => null, // Add if gateway provides expiration
                    'account' => $account,
                    'amount' => $amountValue > 0 ? $amountValue : null,
                    'shareable_url' => $shareableUrl,
                ],
                'message' => 'QR code generated successfully',
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
