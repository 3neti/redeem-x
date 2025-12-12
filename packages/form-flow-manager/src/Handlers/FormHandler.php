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
        $fields = $step->config['fields'] ?? [];
        
        // Build validation rules
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
        $fields = $step->config['fields'] ?? [];
        $rules = $this->buildValidationRules($fields);
        
        // Wrap data in 'data' key to match rule format (data.field_name)
        $wrappedData = ['data' => $data];
        
        validator($wrappedData, $rules)->validate();
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        $fields = $step->config['fields'] ?? [];
        $title = $step->config['title'] ?? 'Form';
        $description = $step->config['description'] ?? null;
        
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
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string',
            'fields.*.type' => 'required|string|in:text,email,date,number,textarea,select,checkbox,file',
            'fields.*.label' => 'nullable|string',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.validation' => 'nullable|array',
            'fields.*.options' => 'nullable|array',
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
            default => ['string'],
        };
    }
}
