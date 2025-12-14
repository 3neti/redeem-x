<?php

declare(strict_types=1);

namespace App\Http\Controllers\Disburse;

use App\Actions\Voucher\ProcessRedemption;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\FormFlowManager\Services\{DriverService, FormFlowService};
use LBHurtado\Voucher\Models\Voucher;

/**
 * Disburse Controller
 * 
 * Handles voucher redemption via dynamic Form Flow Manager.
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
     */
    public function complete(Voucher $voucher): RedirectResponse
    {
        // Get form flow reference ID
        $referenceId = "disburse-{$voucher->code}-" . request()->query('ref', '');
        
        // Retrieve collected data from form flow
        $state = $this->formFlowService->getFlowStateByReference($referenceId);
        
        if (!$state) {
            return redirect()->route('disburse.start')
                ->withErrors(['error' => 'Session expired. Please try again.']);
        }
        
        $collectedData = $state['collected_data'] ?? [];
        
        // Map form flow data to redemption format
        $redemptionData = $this->mapCollectedData($collectedData);
        
        // Process redemption (marks voucher as redeemed, creates cash, disburses, sends notifications)
        ProcessRedemption::run($voucher, $redemptionData);
        
        // Clear form flow session
        $this->formFlowService->clearFlow($state['flow_id']);
        
        // Redirect to success page
        return redirect()->route('disburse.success', ['voucher' => $voucher->code]);
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
        return Inertia::render('disburse/Success', [
            'voucher' => [
                'code' => $voucher->code,
                'amount' => $voucher->amount,
                'formatted_amount' => $voucher->formatted_amount,
                'currency' => $voucher->currency,
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
