<?php

declare(strict_types=1);

namespace App\Actions\Api\Pay;

use Brick\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\Voucher\Models\Voucher;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;

/**
 * Generate Payment QR Code
 *
 * Generate an InstaPay QR code for making payments to settlement/payable vouchers.
 * The QR code encodes bank transfer details - scanning it initiates a real money transfer.
 * 
 * @group Payments
 */
#[Group('Payments')]
class GeneratePaymentQr
{
    public function __construct(
        private PaymentGatewayInterface $gateway
    ) {}
    
    /**
     * Generate payment QR code for voucher
     * 
     * Creates an InstaPay QR code that payers can scan with GCash/Maya to send money.
     * Money goes to the system account and credits the voucher issuer's wallet.
     */
    #[BodyParameter('voucher_code', description: 'Voucher code to pay', type: 'string', example: '2Q2T', required: true)]
    #[BodyParameter('amount', description: 'Payment amount in PHP (whole number)', type: 'number', example: 500, required: true)]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'voucher_code' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);
        
        $voucherCode = $request->input('voucher_code');
        $amountValue = (float) $request->input('amount');
        $currency = config('disbursement.currency', 'PHP');
        
        // Find voucher
        $voucher = Voucher::where('code', $voucherCode)->first();
        
        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found',
            ], 404);
        }
        
        // Validate voucher can accept payment
        if (!$voucher->canAcceptPayment()) {
            return response()->json([
                'success' => false,
                'message' => 'This voucher cannot accept payments',
            ], 422);
        }
        
        // Validate amount against remaining
        $remaining = $voucher->getRemaining();
        if ($amountValue > $remaining) {
            return response()->json([
                'success' => false,
                'message' => "Amount exceeds remaining balance. Maximum: ₱{$remaining}",
            ], 422);
        }
        
        // Get system account for receiving payments
        $systemAccount = config('payment-gateway.system_account');
        
        if (!$systemAccount) {
            return response()->json([
                'success' => false,
                'message' => 'System account not configured',
            ], 500);
        }
        
        // Create cache key
        $cacheKey = "payment_qr:{$voucherCode}:{$amountValue}";
        $cacheTtl = 300; // 5 minutes cache (shorter than wallet QR)
        
        try {
            // Check cache first
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                Log::info('[GeneratePaymentQr] Returning cached payment QR', [
                    'voucher_code' => $voucherCode,
                    'amount' => $amountValue,
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $cachedData,
                    'message' => 'Payment QR code generated successfully',
                    'cached' => true,
                ]);
            }
            
            Log::info('[GeneratePaymentQr] Generating new payment QR', [
                'voucher_code' => $voucherCode,
                'amount' => $amountValue,
                'system_account' => $systemAccount,
            ]);
            
            // Create Money object
            $money = Money::of($amountValue, $currency);
            
            // Prepare merchant data (use voucher context)
            $merchantData = [
                'merchant' => [
                    'name' => config('app.name', 'redeem-x'),
                    'city' => 'Manila',
                    'description' => "Payment for voucher {$voucherCode}",
                ],
                'voucher_code' => $voucherCode,
            ];
            
            // Generate QR code via gateway
            $qrCode = $this->gateway->generate($systemAccount, $money, $merchantData);
            
            Log::info('[GeneratePaymentQr] Payment QR generated successfully', [
                'voucher_code' => $voucherCode,
                'amount' => $amountValue,
            ]);
            
            // Prepare response data
            $responseData = [
                'qr_code' => $this->ensureDataUrl($qrCode),
                'qr_id' => 'PAYMENT-QR-' . strtoupper(uniqid()),
                'account' => $systemAccount,
                'amount' => $amountValue,
                'voucher_code' => $voucherCode,
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
            ];
            
            // Cache the QR code data
            Cache::put($cacheKey, $responseData, $cacheTtl);
            
            return response()->json([
                'success' => true,
                'data' => $responseData,
                'message' => 'Payment QR code generated successfully',
                'cached' => false,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('[GeneratePaymentQr] Failed to generate payment QR', [
                'voucher_code' => $voucherCode,
                'amount' => $amountValue,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payment QR code: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Ensure QR code is a data URL.
     */
    private function ensureDataUrl(string $qrCode): string
    {
        if (str_starts_with($qrCode, 'data:')) {
            return $qrCode;
        }
        
        return 'data:image/png;base64,' . $qrCode;
    }
}
