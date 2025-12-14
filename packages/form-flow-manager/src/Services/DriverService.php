<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Services;

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Arr;

/**
 * Driver Service
 * 
 * Transforms VoucherInstructionsData to FormFlowInstructionsData using YAML config.
 */
class DriverService
{
    protected array $config;
    protected ?TemplateProcessor $templateProcessor = null;
    
    /**
     * Load driver config from YAML file
     */
    public function loadConfig(string $driverName = 'voucher-redemption'): void
    {
        $path = config_path("form-flow-drivers/{$driverName}.yaml");
        
        if (!File::exists($path)) {
            throw new \RuntimeException("Driver config not found: {$path}");
        }
        
        $this->config = Yaml::parseFile($path);
    }
    
    /**
     * Get or create TemplateProcessor instance
     */
    protected function getTemplateProcessor(): TemplateProcessor
    {
        if (!$this->templateProcessor) {
            $this->templateProcessor = new TemplateProcessor();
        }
        
        return $this->templateProcessor;
    }
    
    /**
     * Transform voucher to form flow instructions
     */
    public function transform(Voucher $voucher): FormFlowInstructionsData
    {
        if (!isset($this->config)) {
            $this->loadConfig();
        }
        
        // Check feature flag to determine processing mode
        if (config('form-flow.use_yaml_driver', false)) {
            return $this->transformWithYaml($voucher);
        }
        
        return $this->transformWithPhp($voucher);
    }
    
    /**
     * Transform using YAML driver configuration
     */
    protected function transformWithYaml(Voucher $voucher): FormFlowInstructionsData
    {
        $context = $this->buildContext($voucher);
        
        return FormFlowInstructionsData::from([
            'reference_id' => $this->processReferenceIdFromYaml($context),
            'steps' => $this->processStepsFromYaml($context),
            'callbacks' => $this->processCallbacksFromYaml($context),
        ]);
    }
    
    /**
     * Transform using hardcoded PHP methods (legacy/fallback)
     * 
     * @deprecated 1.1.0 Use YAML driver instead. Will be removed in 2.0.0.
     */
    protected function transformWithPhp(Voucher $voucher): FormFlowInstructionsData
    {
        // Build reference ID
        $referenceId = "disburse-{$voucher->code}-" . time();
        
        // Build steps from voucher instructions
        $steps = $this->buildSteps($voucher);
        
        // Build callbacks
        $callbacks = [
            'on_complete' => url("/disburse/{$voucher->code}/complete"),
            'on_cancel' => url('/disburse'),
        ];
        
        return FormFlowInstructionsData::from([
            'reference_id' => $referenceId,
            'steps' => $steps,
            'callbacks' => $callbacks,
        ]);
    }
    
    /**
     * Build form flow steps from voucher
     * 
     * @deprecated 1.1.0 Use YAML driver instead. Will be removed in 2.0.0.
     */
    protected function buildSteps(Voucher $voucher): array
    {
        $steps = [];
        
        // Step 0: Wallet (always first)
        $steps[] = $this->buildWalletStep($voucher);
        
        // Dynamic steps from inputs.fields
        $inputFields = $voucher->instructions->inputs->fields ?? [];
        
        foreach ($inputFields as $field) {
            // Convert enum to string if needed
            $fieldName = is_object($field) && method_exists($field, '__toString') ? (string)$field : (is_object($field) && isset($field->value) ? $field->value : $field);
            $step = $this->buildStepForField($fieldName, $voucher);
            if ($step) {
                $steps[] = $step;
            }
        }
        
        return $steps;
    }
    
    /**
     * Build wallet collection step
     * 
     * @deprecated 1.1.0 Use YAML driver instead. Will be removed in 2.0.0.
     */
    protected function buildWalletStep(Voucher $voucher): array
    {
        $instructions = $voucher->instructions;
        $amount = $instructions->cash->amount;
        $currency = $instructions->cash->currency;
        $settlementRail = $instructions->cash->settlement_rail?->value ?? 'INSTAPAY';
        
        // Format amount for display
        $formattedAmount = number_format($amount / 100, 2);
        $amountDisplay = "₱{$formattedAmount}";
        
        return [
            'handler' => 'form',
            'config' => [
                'title' => 'Wallet Information',
                'description' => "Redeeming voucher {$voucher->code} - {$amountDisplay} from {$voucher->owner->name}",
                'auto_sync' => [
                    'enabled' => true,
                    'source_field' => 'mobile',
                    'target_field' => 'account_number',
                    'condition_field' => 'settlement_rail',
                    'condition_values' => ['INSTAPAY'],
                    'debounce_ms' => 1500,
                ],
                'variables' => [
                    '$voucherCode' => $voucher->code,
                    '$voucherAmount' => $amount,
                    '$voucherCurrency' => $currency,
                    '$settlementRail' => $settlementRail,
                    '$defaultCountry' => 'PH',
                    '$defaultBank' => 'GXCHPHM2XXX',
                ],
                'fields' => [
                    ['name' => 'amount', 'type' => 'number', 'label' => 'Amount', 'default' => '$voucherAmount', 'readonly' => true, 'required' => true],
                    ['name' => 'settlement_rail', 'type' => 'settlement_rail', 'label' => 'Payment Method', 'default' => '$settlementRail', 'required' => true],
                    ['name' => 'mobile', 'type' => 'text', 'label' => 'Mobile Number', 'placeholder' => '+639171234567', 'required' => true],
                    ['name' => 'recipient_country', 'type' => 'recipient_country', 'label' => 'Country', 'default' => '$defaultCountry', 'readonly' => true, 'required' => true],
                    ['name' => 'bank_code', 'type' => 'bank_account', 'label' => 'Bank/EMI', 'default' => '$defaultBank', 'required' => true],
                    ['name' => 'account_number', 'type' => 'text', 'label' => 'Account Number', 'placeholder' => '1234567890', 'required' => true],
                ],
            ],
        ];
    }
    
    /**
     * Build step for specific input field
     * 
     * @deprecated 1.1.0 Use YAML driver instead. Will be removed in 2.0.0.
     */
    protected function buildStepForField(string $field, Voucher $voucher): ?array
    {
        return match ($field) {
            'mobile' => null, // Already in wallet step
            'name', 'email', 'birth_date', 'address' => $this->buildTextFieldsStep($voucher),
            'selfie' => $this->buildSelfieStep(),
            'location' => $this->buildLocationStep(),
            'signature' => $this->buildSignatureStep(),
            'kyc' => $this->buildKYCStep(),
            default => null,
        };
    }
    
    /**
     * Build combined text fields step
     * 
     * @deprecated 1.1.0 Use YAML driver instead. Will be removed in 2.0.0.
     */
    protected function buildTextFieldsStep(Voucher $voucher): ?array
    {
        static $built = false;
        
        if ($built) {
            return null; // Only build once
        }
        
        $built = true;
        $inputFields = $voucher->instructions->inputs->fields ?? [];
        
        // Convert enums to strings for comparison
        $fieldNames = array_map(fn($f) => is_object($f) && isset($f->value) ? $f->value : $f, $inputFields);
        $fields = [];
        
        if (in_array('name', $fieldNames)) {
            $fields[] = ['name' => 'full_name', 'type' => 'text', 'label' => 'Full Name', 'required' => true];
        }
        
        if (in_array('email', $fieldNames)) {
            $fields[] = ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true];
        }
        
        if (in_array('birth_date', $fieldNames)) {
            $fields[] = ['name' => 'birth_date', 'type' => 'date', 'label' => 'Birth Date', 'required' => true];
        }
        
        if (in_array('address', $fieldNames)) {
            $fields[] = ['name' => 'address', 'type' => 'textarea', 'label' => 'Address', 'required' => false];
        }
        
        if (empty($fields)) {
            return null;
        }
        
        return [
            'handler' => 'form',
            'config' => [
                'title' => 'Personal Information',
                'description' => 'Please provide your details',
                'fields' => $fields,
            ],
        ];
    }
    
    /**
     * Build selfie capture step
     * 
     * @deprecated 1.1.0 Use YAML driver instead. Will be removed in 2.0.0.
     */
    protected function buildSelfieStep(): array
    {
        return [
            'handler' => 'selfie',
            'config' => [
                'title' => 'Take a Selfie',
                'description' => 'Please take a clear selfie for verification',
                'width' => 640,
                'height' => 480,
                'quality' => 0.9,
            ],
        ];
    }
    
    /**
     * Build location capture step
     * 
     * @deprecated 1.1.0 Use YAML driver instead. Will be removed in 2.0.0.
     */
    protected function buildLocationStep(): array
    {
        return [
            'handler' => 'location',
            'config' => [
                'title' => 'Share Your Location',
                'description' => 'We need your current location for verification',
                'require_address' => true,
                'capture_snapshot' => true,
            ],
        ];
    }
    
    /**
     * Build signature capture step
     * 
     * @deprecated 1.1.0 Use YAML driver instead. Will be removed in 2.0.0.
     */
    protected function buildSignatureStep(): array
    {
        return [
            'handler' => 'signature',
            'config' => [
                'title' => 'Digital Signature',
                'description' => 'Please provide your digital signature',
                'width' => 600,
                'height' => 256,
                'quality' => 0.85,
                'line_width' => 2,
            ],
        ];
    }
    
    /**
     * Build KYC verification step
     * 
     * @deprecated 1.1.0 Use YAML driver instead. Will be removed in 2.0.0.
     */
    protected function buildKYCStep(): array
    {
        return [
            'handler' => 'kyc',
            'config' => [
                'title' => 'Identity Verification - KYC',
                'description' => 'Complete identity verification to proceed',
            ],
        ];
    }
    
    // ========================================================================
    // YAML Processing Methods (New)
    // ========================================================================
    
    /**
     * Build context from voucher for template processing
     */
    protected function buildContext(Voucher $voucher): array
    {
        $instructions = $voucher->instructions;
        $inputFields = $instructions->inputs->fields ?? [];
        
        // Convert enum fields to strings for comparison
        $fieldNames = array_map(
            fn($f) => is_object($f) && isset($f->value) ? $f->value : (string)$f,
            $inputFields
        );
        
        return [
            'code' => $voucher->code,
            'amount' => (int) ($instructions->cash->amount ?? 0),
            'currency' => $instructions->cash->currency ?? 'PHP',
            'owner_name' => $voucher->owner->name ?? 'Unknown',
            'base_url' => url(''),
            'timestamp' => time(),
            
            // Field presence flags for conditional rendering
            'has_name' => in_array('name', $fieldNames),
            'has_email' => in_array('email', $fieldNames),
            'has_birth_date' => in_array('birth_date', $fieldNames),
            'has_address' => in_array('address', $fieldNames),
            'has_location' => in_array('location', $fieldNames),
            'has_selfie' => in_array('selfie', $fieldNames),
            'has_signature' => in_array('signature', $fieldNames),
            'has_kyc' => in_array('kyc', $fieldNames),
            
            // Full voucher data for advanced templates
            'voucher' => [
                'code' => $voucher->code,
                'instructions' => [
                    'cash' => [
                        'amount' => $instructions->cash->amount ?? 0,
                        'currency' => $instructions->cash->currency ?? 'PHP',
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Process reference ID from YAML template
     */
    protected function processReferenceIdFromYaml(array $context): string
    {
        $template = $this->config['reference_id'] ?? 'disburse-{{ code }}-{{ timestamp }}';
        return $this->getTemplateProcessor()->process($template, $context);
    }
    
    /**
     * Process callbacks from YAML templates
     */
    protected function processCallbacksFromYaml(array $context): array
    {
        $callbacksConfig = $this->config['callbacks'] ?? [];
        $processor = $this->getTemplateProcessor();
        
        return [
            'on_complete' => $processor->process($callbacksConfig['on_complete'] ?? '', $context),
            'on_cancel' => $processor->process($callbacksConfig['on_cancel'] ?? '', $context),
        ];
    }
    
    /**
     * Process steps from YAML configuration
     */
    protected function processStepsFromYaml(array $context): array
    {
        $stepsConfig = $this->config['steps'] ?? [];
        $processor = $this->getTemplateProcessor();
        $steps = [];
        
        foreach ($stepsConfig as $stepName => $stepConfig) {
            // Check condition (if specified)
            if (isset($stepConfig['condition'])) {
                $conditionResult = $processor->process($stepConfig['condition'], $context);
                if (!$this->evaluateCondition($conditionResult)) {
                    continue; // Skip this step
                }
            }
            
            // Process step configuration
            $step = [
                'handler' => $stepConfig['handler'] ?? 'form',
                'config' => [],
            ];
            
            // Process title and description
            if (isset($stepConfig['title'])) {
                $step['config']['title'] = $processor->process($stepConfig['title'], $context);
            }
            if (isset($stepConfig['description'])) {
                $step['config']['description'] = $processor->process($stepConfig['description'], $context);
            }
            
            // Process fields for 'form' handler
            if ($stepConfig['handler'] === 'form' && isset($stepConfig['fields'])) {
                $step['config']['fields'] = $this->processFields($stepConfig['fields'], $context);
            }
            
            // Process config section
            if (isset($stepConfig['config'])) {
                $step['config'] = array_merge(
                    $step['config'],
                    $processor->processArray($stepConfig['config'], $context)
                );
            }
            
            // Only add step if it has fields (for form handler) or is not a form handler
            if ($step['handler'] !== 'form' || !empty($step['config']['fields'])) {
                $steps[] = $step;
            }
        }
        
        return $steps;
    }
    
    /**
     * Process fields array with conditions
     */
    protected function processFields(array $fields, array $context): array
    {
        $processor = $this->getTemplateProcessor();
        $processedFields = [];
        
        foreach ($fields as $field) {
            // Check field condition (if specified)
            if (isset($field['condition'])) {
                $conditionResult = $processor->process($field['condition'], $context);
                if (!$this->evaluateCondition($conditionResult)) {
                    continue; // Skip this field
                }
            }
            
            // Remove condition from field config (not needed in output)
            $fieldCopy = $field;
            unset($fieldCopy['condition']);
            
            // Process field templates
            $processedField = $processor->processArray($fieldCopy, $context);
            $processedFields[] = $processedField;
        }
        
        return $processedFields;
    }
    
    /**
     * Evaluate a condition result
     */
    protected function evaluateCondition(string $result): bool
    {
        $result = trim($result);
        
        // Empty string or 'false' = false
        if ($result === '' || $result === 'false' || $result === '0') {
            return false;
        }
        
        // 'true' or any non-empty string = true
        return true;
    }
}
