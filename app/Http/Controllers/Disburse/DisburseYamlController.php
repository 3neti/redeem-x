<?php

declare(strict_types=1);

namespace App\Http\Controllers\Disburse;

use Illuminate\Http\RedirectResponse;
use LBHurtado\FormFlowManager\Services\{DriverService, FormFlowService};
use LBHurtado\Voucher\Models\Voucher;

/**
 * Disburse YAML Controller
 * 
 * A/B testing variant that forces YAML driver mode for form flow generation.
 * Extends DisburseController with only reference ID prefix override.
 */
class DisburseYamlController extends DisburseController
{
    public function __construct(
        protected DriverService $driverService,
        protected FormFlowService $formFlowService
    ) {
        parent::__construct($driverService, $formFlowService);
        
        // Force YAML driver mode for A/B testing
        config(['form-flow.use_yaml_driver' => true]);
    }
    
    /**
     * Initiate form flow with YAML driver and custom reference prefix
     */
    public function initiateFlow(string $code): RedirectResponse
    {
        $voucher = Voucher::where('code', $code)->firstOrFail();
        
        // Transform voucher using YAML driver (forced via constructor)
        $instructions = $this->driverService->transform($voucher);
        
        // Override reference_id to use 'disburse-yaml-' prefix for differentiation
        $instructions->reference_id = 'disburse-yaml-' . $voucher->code;
        
        // Start form flow
        $state = $this->formFlowService->startFlow($instructions);
        
        // Redirect to form flow
        return redirect("/form-flow/{$state['flow_id']}");
    }
}
