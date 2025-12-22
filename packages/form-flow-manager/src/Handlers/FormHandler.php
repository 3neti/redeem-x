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
        $autoSync = $resolvedConfig['auto_sync'] ?? null;
        
        return Inertia::render('form-flow/core/GenericForm', [
            'flow_id' => $context['flow_id'] ?? null,
            'step_index' => $context['step_index'] ?? 0,
            'title' => $title,
            'description' => $description,
            'fields' => $fields,
            'auto_sync' => $autoSync,
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'variables' => 'nullable|array',
            'auto_sync' => 'nullable|array',
            'auto_sync.enabled' => 'nullable|boolean',
            'auto_sync.source_field' => 'nullable|string',
            'auto_sync.target_field' => 'nullable|string',
            'auto_sync.condition_field' => 'nullable|string',
            'auto_sync.condition_values' => 'nullable|array',
            'auto_sync.debounce_ms' => 'nullable|integer',
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
        
        // Auto-populate variables from collected data
        // Creates both index-based ($step0_fieldname) and name-based ($step_name.fieldname) variables
        foreach ($collectedData as $stepIndex => $stepData) {
            if (is_array($stepData)) {
                $stepName = $stepData['_step_name'] ?? null;
                
                foreach ($stepData as $key => $value) {
                    // Skip internal metadata
                    if ($key === '_step_name') {
                        continue;
                    }
                    
                    // Index-based (backward compatibility)
                    $variables["\$step{$stepIndex}_{$key}"] = $value;
                    
                    // Name-based (new dot notation)
                    if ($stepName) {
                        $variables["\${$stepName}.{$key}"] = $value;
                    }
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
                        $resolvedValue = $variables[$varName];
                        // Check if resolved value is still a variable reference
                        if (is_string($resolvedValue) && str_starts_with($resolvedValue, '$')) {
                            // Nested variable is unresolved, clear the property
                            $field[$property] = null;
                        } else {
                            $field[$property] = $resolvedValue;
                        }
                    } else {
                        // Clear the property if variable doesn't exist
                        $field[$property] = null;
                    }
                }
            }
        }
        
        // Resolve description
        if (isset($config['description']) && is_string($config['description'])) {
            // Replace all variable references in description
            $description = $config['description'];
            foreach ($variables as $varName => $varValue) {
                if (is_scalar($varValue)) {
                    $description = str_replace($varName, (string) $varValue, $description);
                }
            }
            $config['description'] = $description;
        }
        
        // Return config with resolved fields (remove variables block as it's no longer needed)
        $resolvedConfig = $config;
        $resolvedConfig['fields'] = $fields;
        unset($resolvedConfig['variables']);
        
        return $resolvedConfig;
    }
}
