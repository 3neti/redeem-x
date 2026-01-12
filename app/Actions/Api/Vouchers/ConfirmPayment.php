<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Models\PaymentRequest;
use App\Services\DisbursementService;
use App\Settings\VoucherSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;

/**
 * Confirm Voucher Payment
 *
 * Manually confirm a payment received for a settlement/payable voucher.
 * Only the voucher owner can confirm payments.
 * 
 * @group Vouchers
 * @authenticated
 */
#[Group('Vouchers')]
class ConfirmPayment
{
    /**
     * Confirm payment for a voucher
     * 
     * Two modes:
     * 1. Manual: Provide voucher_code, amount, payment_id, payer (manual entry)
     * 2. From PaymentRequest: Provide only payment_request_id (one-click confirm)
     */
    #[BodyParameter('payment_request_id', description: 'Payment request ID (for QR payments)', type: 'integer', example: 1)]
    #[BodyParameter('voucher_code', description: 'Voucher code (manual mode)', type: 'string', example: '2Q2T')]
    #[BodyParameter('amount', description: 'Payment amount in PHP (manual mode)', type: 'number', example: 100)]
    #[BodyParameter('payment_id', description: 'Optional payment reference ID (manual mode)', type: 'string', example: 'GCASH-123456')]
    #[BodyParameter('payer', description: 'Optional payer mobile/email (manual mode)', type: 'string', example: '09171234567')]
    #[BodyParameter('disburse_now', description: 'Auto-disburse to bank account (optional)', type: 'boolean', example: false)]
    #[BodyParameter('bank_account_id', description: 'Bank account UUID to disburse to (required if disburse_now=true)', type: 'string', example: 'uuid-123')]
    #[BodyParameter('remember_choice', description: 'Remember disbursement choice for future payments (optional)', type: 'boolean', example: false)]
    public function __invoke(Request $request, DisbursementService $disbursementService, VoucherSettings $settings): JsonResponse
    {
        $request->validate([
            'payment_request_id' => ['nullable', 'integer', 'exists:payment_requests,id'],
            'voucher_code' => ['required_without:payment_request_id', 'string'],
            'amount' => ['required_without:payment_request_id', 'numeric', 'min:0.01'],
            'payment_id' => ['nullable', 'string'],
            'payer' => ['nullable', 'string'],
            'disburse_now' => ['nullable', 'boolean'],
            'bank_account_id' => ['required_if:disburse_now,true', 'nullable', 'string'],
            'remember_choice' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $paymentRequestId = $request->input('payment_request_id');
        
        // Mode 1: Confirm from PaymentRequest (QR payment)
        if ($paymentRequestId) {
            Log::info('[ConfirmPayment] Looking up payment request', [
                'payment_request_id' => $paymentRequestId,
            ]);
            
            $paymentRequest = PaymentRequest::with('voucher.owner')->find($paymentRequestId);
            
            if (!$paymentRequest) {
                Log::warning('[ConfirmPayment] Payment request not found', [
                    'payment_request_id' => $paymentRequestId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment request not found',
                ], 404);
            }
            
            Log::info('[ConfirmPayment] Payment request found', [
                'payment_request_id' => $paymentRequestId,
                'status' => $paymentRequest->status,
                'voucher_code' => $paymentRequest->voucher->code ?? 'N/A',
            ]);
            
            // Check if payment request is awaiting confirmation
            if ($paymentRequest->status !== 'awaiting_confirmation') {
                Log::warning('[ConfirmPayment] Payment request already processed', [
                    'payment_request_id' => $paymentRequestId,
                    'current_status' => $paymentRequest->status,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment request is not awaiting confirmation',
                ], 422);
            }
            
            $voucher = $paymentRequest->voucher;
            $voucherCode = $voucher->code;
            $amountInMajorUnits = $paymentRequest->getAmountInMajorUnits();
            $paymentId = $paymentRequest->reference_id;
            $payer = $paymentRequest->payer_info['name'] ?? $paymentRequest->payer_info['mobile'] ?? null;
        }
        // Mode 2: Manual confirmation
        else {
            $voucherCode = $request->input('voucher_code');
            $amountInMajorUnits = (float) $request->input('amount');
            $paymentId = $request->input('payment_id') ?: 'WEB-' . now()->timestamp;
            $payer = $request->input('payer');
            
            // Find voucher with owner
            $voucher = Voucher::with('owner')->where('code', $voucherCode)->first();
        }

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found',
            ], 404);
        }

        // Check ownership
        if (!$voucher->owner || $voucher->owner->id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this voucher',
            ], 403);
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
        if ($amountInMajorUnits > $remaining) {
            return response()->json([
                'success' => false,
                'message' => "Amount exceeds remaining balance. Maximum: ₱{$remaining}",
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get or create cash entity (voucher's wallet)
            // Settlement vouchers don't have cash entity until first payment
            $cash = $voucher->cash;
            if (!$cash) {
                Log::info('[ConfirmPayment] Creating cash entity for first payment', [
                    'voucher_code' => $voucher->code,
                ]);
                
                $cash = \LBHurtado\Cash\Models\Cash::create([
                    'amount' => 0,
                    'currency' => 'PHP',
                ]);
                $voucher->cashable()->associate($cash);
                $voucher->save();
            }
            
            if (!$cash->wallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher wallet not initialized',
                ], 500);
            }
            
            Log::info('[ConfirmPayment] Processing payment confirmation', [
                'voucher_code' => $voucher->code,
                'amount' => $amountInMajorUnits,
                'payment_id' => $paymentId,
                'cash_balance_before' => $cash->balanceFloat,
            ]);

            // Transfer from system wallet to voucher's cash wallet using TopupWalletAction
            // Real world: Payer's GCash → Owner's Bank Account (via NetBank)
            // App world: System Wallet → Voucher Wallet (maintains double-entry accounting)
            $transfer = TopupWalletAction::run($cash, $amountInMajorUnits);
            
            // Add metadata to the transfer
            $transfer->withdraw->update([
                'meta' => array_merge($transfer->withdraw->meta ?? [], [
                    'flow' => 'pay',
                    'voucher_code' => $voucher->code,
                    'payment_id' => $paymentId,
                    'payer' => $payer,
                    'confirmed_by' => 'web',
                    'confirmed_by_user_id' => $user->id,
                ]),
            ]);
            
            $transfer->deposit->update([
                'meta' => array_merge($transfer->deposit->meta ?? [], [
                    'flow' => 'pay',
                    'voucher_code' => $voucher->code,
                    'payment_id' => $paymentId,
                    'payer' => $payer,
                    'confirmed_by' => 'web',
                    'confirmed_by_user_id' => $user->id,
                ]),
            ]);
            
            Log::info('[ConfirmPayment] Payment confirmed successfully', [
                'voucher_code' => $voucher->code,
                'transfer_uuid' => $transfer->uuid,
                'cash_balance_after' => $cash->fresh()->balanceFloat,
            ]);
            
            // Mark payment request as confirmed if it exists
            if (isset($paymentRequest)) {
                $paymentRequest->markAsConfirmed();
            }

            DB::commit();
            
            // Auto-disbursement logic (if requested)
            // NOTE: This runs AFTER commit, so payment is already confirmed
            // If disbursement fails, payment still succeeds
            $disbursementResult = null;
            if ($request->boolean('disburse_now')) {
                try {
                    Log::info('[ConfirmPayment] Attempting auto-disbursement', [
                        'voucher_code' => $voucher->code,
                        'amount' => $amountInMajorUnits,
                        'bank_account_id' => $request->input('bank_account_id'),
                    ]);
                    
                    $disbursementResult = $this->handleAutoDisbursement(
                        $request,
                        $voucher,
                        $amountInMajorUnits,
                        $disbursementService
                    );
                    
                    Log::info('[ConfirmPayment] Auto-disbursement completed', [
                        'success' => $disbursementResult['success'] ?? false,
                        'message' => $disbursementResult['message'] ?? 'N/A',
                    ]);
                } catch (\Throwable $e) {
                    // Disbursement failed, but payment already confirmed
                    Log::error('[ConfirmPayment] Auto-disbursement exception', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    $disbursementResult = [
                        'success' => false,
                        'message' => 'Disbursement failed: ' . $e->getMessage(),
                        'error' => 'exception',
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'data' => [
                    'amount' => $amountInMajorUnits,
                    'payment_id' => $paymentId,
                    'new_paid_total' => $voucher->fresh()->getPaidTotal(),
                    'remaining' => $voucher->fresh()->getRemaining(),
                    'disbursement' => $disbursementResult,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Handle auto-disbursement after payment confirmation.
     * 
     * @param Request $request
     * @param Voucher $voucher
     * @param float $amount
     * @param DisbursementService $disbursementService
     * @return array|null Disbursement result or null
     */
    protected function handleAutoDisbursement(
        Request $request,
        Voucher $voucher,
        float $amount,
        DisbursementService $disbursementService
    ): ?array {
        $user = $request->user();
        $bankAccountId = $request->input('bank_account_id');
        
        // Get bank account
        $bankAccount = $user->getBankAccountById($bankAccountId);
        if (!$bankAccount) {
            return [
                'success' => false,
                'message' => 'Bank account not found',
            ];
        }
        
        // Check if voucher is fully paid
        if ($voucher->fresh()->getRemaining() > 0) {
            return [
                'success' => false,
                'message' => 'Voucher not fully paid yet. Auto-disbursement only works for fully paid vouchers.',
            ];
        }
        
        // Perform disbursement
        $result = $disbursementService->disburse(
            $user,
            $amount,
            [
                'bank_code' => $bankAccount['bank_code'],
                'account_number' => $bankAccount['account_number'],
            ],
            null, // Auto-select rail
            [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'payment_type' => 'settlement',
            ]
        );
        
        // Save user preference if requested
        if ($request->boolean('remember_choice')) {
            $preferences = $user->ui_preferences ?? [];
            $preferences['auto_disburse_on_settlement'] = true;
            $preferences['auto_disburse_bank_account_id'] = $bankAccountId;
            $user->ui_preferences = $preferences;
            $user->save();
        }
        
        return $result;
    }
}
