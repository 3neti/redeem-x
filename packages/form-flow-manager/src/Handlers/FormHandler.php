<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Handlers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

/**
 * Built-in Form Handler
 * 
 * Generic handler for collecting basic form inputs when no specialized
 * plugin exists (location, selfie, signature, kyc).
 * 
 * Supports: text, email, date, number, textarea, select, checkbox, file
 */
class FormHandler implements FormHandlerInterface
{
    public function getName(): string
    {
        return 'form';
    }
    }
    
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        // Resolve variables with collected data from previous steps
        $collectedData = $context['collected_data'] ?? [];
        $resolvedConfig = $this->resolveVariables($step->config, $collectedData);
        $fields = $resolvedConfig['fields'] ?? [];
        
        // Build validation rules with resolved variables
        $rules = $this->buildValidationRules($fields);
        
        // Validate the submitted data
        $validated = $request->validate($rules);
        
        // Return the validated data
        return $validated['data'] ?? [];
    }
    
    public function validate(array $data, array $rules): bool
    {
        // Build validation rules from FormFlowStepData
        validator($data, $rules)->validate();
        return true;
    }
    
    /**
     * Validate data for a specific step
     * 
     * @param FormFlowStepData $step
     * @param array $data
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateStep(FormFlowStepData $step, array $data): bool
    {
        // Note: This method is called without context, so we can't resolve
        // variables here. Variables should already be resolved when this is called.
        $fields = $step->config['fields'] ?? [];
        $rules = $this->buildValidationRules($fields);
        
        // Wrap data in 'data' key to match rule format (data.field_name)
        $wrappedData = ['data' => $data];
        
        validator($wrappedData, $rules)->validate();
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        // Resolve variables with collected data from previous steps
        $collectedData = $context['collected_data'] ?? [];
        $resolvedConfig = $this->resolveVariables($step->config, $collectedData);
        
        $fields = $resolvedConfig['fields'] ?? [];
        $title = $resolvedConfig['title'] ?? 'Form';
        $description = $resolvedConfig['description'] ?? null;
        
        return Inertia::render('form-flow/core/GenericForm', [
            'flow_id' => $context['flow_id'] ?? null,
            'step_index' => $context['step_index'] ?? 0,
            'title' => $title,
            'description' => $description,
            'fields' => $fields,
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'variables' => 'nullable|array',
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string',
            'fields.*.type' => 'required|string|in:text,email,date,number,textarea,select,checkbox,file,recipient_country,settlement_rail,bank_account',
            'fields.*.label' => 'nullable|string',
            'fields.*.placeholder' => 'nullable|string',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.validation' => 'nullable|array',
            'fields.*.options' => 'nullable|array',
            'fields.*.default' => 'nullable',
            'fields.*.min' => 'nullable',
            'fields.*.max' => 'nullable',
            'fields.*.step' => 'nullable',
            'fields.*.readonly' => 'nullable|boolean',
            'fields.*.disabled' => 'nullable|boolean',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
        ];
    }
    
    /**
     * Build Laravel validation rules from field definitions
     */
    protected function buildValidationRules(array $fields): array
    {
        $rules = [];
        
        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? null;
            if (!$fieldName) {
                continue;
            }
            
            $fieldRules = [];
            
            // Add required rule
            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }
            
            // Add type-specific rules
            $type = $field['type'] ?? 'text';
            $fieldRules = array_merge($fieldRules, $this->getTypeRules($type));
            
            // Add min/max constraints for numeric and date fields
            if (in_array($type, ['number', 'date'])) {
                if (isset($field['min'])) {
                    $fieldRules[] = 'min:' . $field['min'];
                }
                if (isset($field['max'])) {
                    $fieldRules[] = 'max:' . $field['max'];
                }
            }
            
            // Add custom validation rules
            if (isset($field['validation']) && is_array($field['validation'])) {
                $fieldRules = array_merge($fieldRules, $field['validation']);
            }
            
            $rules["data.{$fieldName}"] = $fieldRules;
        }
        
        return $rules;
    }
    
    /**
     * Get validation rules based on field type
     */
    protected function getTypeRules(string $type): array
    {
        return match($type) {
            'email' => ['email'],
            'date' => ['date'],
            'number' => ['numeric'],
            'checkbox' => ['boolean'],
            'file' => ['file'],
            'textarea' => ['string'],
            'text' => ['string'],
            'select' => ['string'],
            'recipient_country' => ['string', 'size:2'], // ISO 3166-1 alpha-2
            'settlement_rail' => ['nullable', 'string', 'in:INSTAPAY,PESONET'],
            'bank_account' => ['string', 'size:11'], // SWIFT/BIC code format
            default => ['string'],
        };
    }
    
    /**
     * Resolve variable references in configuration
     * 
     * Variables are defined with $ prefix: {$country: 'PH', $amount: 100}
     * Field properties can reference variables: {default: '$country'}
     * Supports nested references: $var1 -> $var2 -> 'value'
     * 
     * @param array $config Step configuration with variables and fields
     * @param array $collectedData Optional collected data from previous steps
     * @return array Configuration with resolved variables
     */
    protected function resolveVariables(array $config, array $collectedData = []): array
    {
        $variables = $config['variables'] ?? [];
        
        // Auto-populate variables from collected data (Phase 2)
        // Format: $step0_fieldname, $step1_fieldname
        foreach ($collectedData as $stepIndex => $stepData) {
            if (is_array($stepData)) {
                foreach ($stepData as $key => $value) {
                    $variables["\$step{$stepIndex}_{$key}"] = $value;
                }
            }
        }
        
        // Resolve nested variable references (up to 10 levels to prevent infinite loops)
        $maxDepth = 10;
        for ($i = 0; $i < $maxDepth; $i++) {
            $hasUnresolved = false;
            
            foreach ($variables as $key => $value) {
                if (is_string($value) && str_starts_with($value, '$')) {
                    // This is a reference to another variable
                    if (isset($variables[$value])) {
                        $variables[$key] = $variables[$value];
                        $hasUnresolved = true;
                    }
                }
            }
            
            if (!$hasUnresolved) {
                break;
            }
        }
        
        // Resolve field properties
        $fields = $config['fields'] ?? [];
        
        foreach ($fields as &$field) {
            // Resolve each property that might contain variable references
            $propertiesToResolve = ['default', 'min', 'max', 'step', 'placeholder'];
            
            foreach ($propertiesToResolve as $property) {
                if (isset($field[$property]) && is_string($field[$property]) && str_starts_with($field[$property], '$')) {
                    $varName = $field[$property];
                    if (isset($variables[$varName])) {
                        $field[$property] = $variables[$varName];
                    }
                }
            }
        }
        
        // Return config with resolved fields (remove variables block as it's no longer needed)
        $resolvedConfig = $config;
        $resolvedConfig['fields'] = $fields;
        unset($resolvedConfig['variables']);
        
        return $resolvedConfig;
    }
}
