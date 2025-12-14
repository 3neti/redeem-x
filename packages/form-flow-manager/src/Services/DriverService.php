<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Services;

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Driver Service
 * 
 * Transforms VoucherInstructionsData to FormFlowInstructionsData using YAML config.
 */
class DriverService
{
    protected array $config;
    
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
     * Transform voucher to form flow instructions
     */
    public function transform(Voucher $voucher): FormFlowInstructionsData
    {
        if (!isset($this->config)) {
            $this->loadConfig();
        }
        
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
}
