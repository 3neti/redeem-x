<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Contracts;

use Illuminate\Http\Request;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

/**
 * Form Handler Interface
 * 
 * Contract for all input collection handlers (location, selfie, signature, KYC, etc.)
 * Each handler package will implement this interface to provide its functionality.
 */
interface FormHandlerInterface
{
    /**
     * Get the handler identifier
     * 
     * @return string Handler name (e.g., 'location', 'selfie', 'kyc')
     */
    public function getName(): string;
    
    /**
     * Handle the input collection
     * 
     * @param Request $request HTTP request
     * @param FormFlowStepData $step Step configuration
     * @param array $context Flow context data
     * @return array Collected data
     */
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array;
    
    /**
     * Validate collected data
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return bool Whether data is valid
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $data, array $rules): bool;
    
    /**
     * Render the handler view
     * 
     * @param FormFlowStepData $step Step configuration
     * @param array $context Flow context data
     * @return \Illuminate\Contracts\View\View|\Inertia\Response
     */
    public function render(FormFlowStepData $step, array $context = []);
    
    /**
     * Get handler-specific configuration schema
     * 
     * @return array Configuration schema
     */
    public function getConfigSchema(): array;
}
