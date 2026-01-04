<?php

declare(strict_types=1);

namespace App\Http\Controllers\Disburse;

use App\Actions\Voucher\ProcessRedemption;
use App\Http\Controllers\Controller;
use App\Services\VoucherRedemptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\FormFlowManager\Services\{DriverService, FormFlowService};
use LBHurtado\Voucher\Exceptions\RedemptionException;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Disburse Controller - PRIMARY REDEMPTION PATH
 * 
 * Handles voucher redemption via dynamic Form Flow Manager.
 * 
 * **Primary Redemption Flow:** This is the main redemption controller.
 * Uses Form Flow Manager for flexible, dynamic input collection based on
 * voucher instructions. Replaces the legacy RedeemController (/redeem).
 * 
 * **Validation:** Integrated with Unified Validation Gateway:
 * - PayableSpecification: Enforces vendor alias restrictions (B2B vouchers)
 * - SecretSpecification: Validates secret codes
 * - MobileSpecification: Validates mobile number restrictions
 * - InputsSpecification: Validates required input fields
 * - KycSpecification: Validates KYC approval status
 * - LocationSpecification: Validates GPS radius restrictions
 * - TimeWindowSpecification: Validates time window restrictions
 * - TimeLimitSpecification: Validates redemption time limits
 * 
 * **Key Features:**
 * - Dynamic form generation via Form Flow Manager
 * - Pluggable input handlers (location, signature, selfie, KYC)
 * - Complete validation before redemption
 * - Supports both authenticated and unauthenticated redemptions
 * - For B2B vouchers (payable restriction), user must be authenticated
 * 
 * @see \App\Services\VoucherRedemptionService
 * @see \LBHurtado\Voucher\Guards\RedemptionGuard
 * @see \App\Http\Controllers\Redeem\RedeemController (deprecated)
 */
class DisburseController extends Controller
{
    public function __construct(
        protected DriverService $driverService,
        protected FormFlowService $formFlowService
    ) {}
    
    /**
     * Show the disburse start page
     */
    public function start(): Response|RedirectResponse
    {
        $code = request()->query('code');
        
        // If code provided and this is Inertia request, validate and redirect
        if ($code && request()->header('X-Inertia')) {
            $code = strtoupper(trim($code));
            
            try {
                $voucher = Voucher::where('code', $code)->firstOrFail();
                
                // Validate voucher status
                if ($voucher->isRedeemed()) {
                    return redirect()->route('disburse.start')
                        ->withInput(['code' => $code])
                        ->withErrors(['code' => 'This voucher has already been redeemed.']);
                }
                
                if ($voucher->isExpired()) {
                    return redirect()->route('disburse.start')
                        ->withInput(['code' => $code])
                        ->withErrors(['code' => 'This voucher has expired.']);
                }
                
                if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
                    return redirect()->route('disburse.start')
                        ->withInput(['code' => $code])
                        ->withErrors(['code' => 'This voucher is not yet active.']);
                }
                
                // Valid voucher, initiate flow
                return $this->initiateFlow($code);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return redirect()->route('disburse.start')
                    ->withInput(['code' => $code])
                    ->withErrors(['code' => 'Invalid voucher code.']);
            }
        }
        
        return Inertia::render('disburse/Start', [
            'initial_code' => old('code', $code),
        ]);
    }
    
    /**
     * Initiate form flow for voucher
     */
    public function initiateFlow(string $code): RedirectResponse
    {
        $voucher = Voucher::where('code', $code)->firstOrFail();
        
        // Transform voucher to form flow instructions
        $instructions = $this->driverService->transform($voucher);
        
        // Start form flow
        $state = $this->formFlowService->startFlow($instructions);
        
        // Redirect to form flow
        return redirect("/form-flow/{$state['flow_id']}");
    }
    
    /**
     * Handle form flow completion (callback)
     * Note: Does NOT redeem automatically - just acknowledges callback
     */
    public function complete(Voucher $voucher)
    {
        // Callback received - form flow complete
        // Do not redeem here - user must click confirm button
        return response()->json([
            'success' => true,
            'message' => 'Flow completed, awaiting user confirmation',
        ]);
    }
    
    /**
     * Process voucher redemption after user confirmation
     */
    public function redeem(Voucher $voucher): RedirectResponse
    {
        // Get reference_id from request (sent from Complete.vue)
        $referenceId = request()->input('reference_id');
        $flowId = request()->input('flow_id');
        
        if (!$referenceId && !$flowId) {
            return redirect()->route('disburse.start')
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }
        
        // Retrieve collected data from form flow
        $state = $referenceId 
            ? $this->formFlowService->getFlowStateByReference($referenceId)
            : $this->formFlowService->getFlowState($flowId);
        
        if (!$state) {
            return redirect()->route('disburse.start')
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }
        
        $collectedData = $state['collected_data'] ?? [];
        
        // Map form flow data to redemption format
        $flatData = $this->mapCollectedData($collectedData);
        
        // Extract mobile and country for PhoneNumber
        $mobile = $flatData['mobile'] ?? null;
        $country = $flatData['recipient_country'] ?? 'PH';
        
        if (!$mobile) {
            return redirect()->route('disburse.start')
                ->withErrors(['error' => 'Mobile number is required.']);
        }
        
        // Create PhoneNumber instance
        $phoneNumber = new \Propaganistas\LaravelPhone\PhoneNumber($mobile, $country);
        
        // Prepare bank account data
        $bankAccount = [
            'bank_code' => $flatData['bank_code'] ?? null,
            'account_number' => $flatData['account_number'] ?? null,
        ];
        
        // Prepare other inputs (exclude wallet fields)
        $inputs = collect($flatData)
            ->except(['mobile', 'recipient_country', 'bank_code', 'account_number', 'amount', 'settlement_rail'])
            ->toArray();
        
        try {
            // Validate using Unified Validation Gateway
            $service = new VoucherRedemptionService();
            $context = $service->resolveContextFromArray([
                'mobile' => $mobile,
                'secret' => $flatData['secret'] ?? null,
                'inputs' => $inputs,
                'bank_account' => $bankAccount,
            ], auth()->user());
            
            $service->validateRedemption($voucher, $context);
            
            // Store idempotency key for redemption tracking
            $idempotencyKey = request()->header('Idempotency-Key');
            if ($idempotencyKey) {
                $voucher->update([
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_created_at' => now(),
                ]);
            }
            
            // Process redemption (marks voucher as redeemed, creates cash, disburses, sends notifications)
            ProcessRedemption::run($voucher, $phoneNumber, $inputs, $bankAccount);
            
            // Clear form flow session
            $this->formFlowService->clearFlow($state['flow_id']);
            
            // Redirect to success page
            return redirect()->route('disburse.success', ['voucher' => $voucher->code])
                ->with('success', 'Voucher redeemed successfully!');
                
        } catch (RedemptionException $e) {
            // Validation failed (secret, mobile, payable mismatch, etc.)
            Log::warning('[DisburseController] Validation failed', [
                'voucher' => $voucher->code,
                'error' => $e->getMessage(),
            ]);
            
            // Return JSON error for AJAX requests, redirect otherwise
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            
            return redirect()->route('disburse.start')
                ->withErrors(['code' => $e->getMessage()]);
                
        } catch (\Throwable $e) {
            Log::error('[DisburseController] Redemption failed', [
                'voucher' => $voucher->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return JSON error for AJAX requests, redirect otherwise
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process redemption: ' . $e->getMessage(),
                ], 422);
            }
            
            return redirect()->route('disburse.start')
                ->withErrors(['code' => 'Failed to process redemption: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Handle flow cancellation
     */
    public function cancel(): RedirectResponse
    {
        return redirect()->route('disburse.start')
            ->with('message', 'Redemption cancelled.');
    }
    
    /**
     * Show success page
     */
    public function success(Voucher $voucher): Response
    {
        $amount = $voucher->instructions->cash->amount ?? 0;
        $currency = $voucher->instructions->cash->currency ?? 'PHP';
        $riderTimeout = $voucher->instructions->rider->redirect_timeout ?? config('redeem.success.redirect.timeout', 10);
        $formattedAmount = '₱' . number_format($amount, 2);
        
        // Process rider message with SuccessContentService
        $successService = app(\App\Services\SuccessContentService::class);
        
        $context = [
            'voucher_code' => $voucher->code,
            'amount' => $formattedAmount,
            'currency' => $currency,
            'mobile' => null, // TODO: Get from redemption if available
        ];
        
        $message = $voucher->instructions->rider->message ?? config('success.default_content');
        $processedContent = $successService->processContent($message, $context);
        
        return Inertia::render('disburse/Success', [
            'voucher' => [
                'code' => $voucher->code,
                'amount' => $amount,
                'formatted_amount' => $formattedAmount,
                'currency' => $currency,
            ],
            'rider' => [
                'message' => $message,
                'processed_content' => $processedContent,
                'url' => $voucher->instructions->rider->url ?? null,
            ],
            'redirect_timeout' => $riderTimeout,
            'config' => [
                'button_labels' => [
                    'continue' => config('success.button_label', 'Continue Now'),
                    'dashboard' => config('success.dashboard_button_label', 'Go to Dashboard'),
                    'redeem_another' => config('success.redeem_another_label', 'Redeem Another'),
                ],
            ],
        ]);
    }
    
    /**
     * Map collected form flow data to redemption format
     */
    protected function mapCollectedData(array $collectedData): array
    {
        $mapped = [];
        
        // Flatten all steps
        foreach ($collectedData as $stepData) {
            if (is_array($stepData)) {
                $mapped = array_merge($mapped, $stepData);
            }
        }
        
        return $mapped;
    }
}
