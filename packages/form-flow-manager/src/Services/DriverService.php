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
 * Transforms VoucherInstructionsData to FormFlowInstructionsData using YAML configuration.
 * This service uses declarative YAML files to define form flow transformations.
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
     * Transform voucher to form flow instructions using YAML driver
     */
    public function transform(Voucher $voucher): FormFlowInstructionsData
    {
        if (!isset($this->config)) {
            $this->loadConfig();
        }
        
        $context = $this->buildContext($voucher);
        
        return FormFlowInstructionsData::from([
            'reference_id' => $this->processReferenceId($context),
            'steps' => $this->processSteps($context),
            'callbacks' => $this->processCallbacks($context),
        ]);
    }
    
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
            
            // Splash configuration
            'splash_enabled' => config('splash.enabled', true) ? 'true' : 'false',
            
            // Field presence flags for conditional rendering
            'has_name' => in_array('name', $fieldNames),
            'has_email' => in_array('email', $fieldNames),
            'has_birth_date' => in_array('birth_date', $fieldNames),
            'has_address' => in_array('address', $fieldNames),
            'has_location' => in_array('location', $fieldNames),
            'has_selfie' => in_array('selfie', $fieldNames),
            'has_signature' => in_array('signature', $fieldNames),
            'has_kyc' => in_array('kyc', $fieldNames),
            'has_otp' => in_array('otp', $fieldNames),
            
            // Rider data for splash page and post-redemption behavior
            'rider' => [
                'message' => $instructions->rider->message ?? null,
                'url' => $instructions->rider->url ?? null,
                'redirect_timeout' => $instructions->rider->redirect_timeout ?? null,
                'splash' => $instructions->rider->splash ?? null,
                'splash_timeout' => $instructions->rider->splash_timeout ?? null,
            ],
            
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
    protected function processReferenceId(array $context): string
    {
        $template = $this->config['reference_id'] ?? 'disburse-{{ code }}-{{ timestamp }}';
        return $this->getTemplateProcessor()->process($template, $context);
    }
    
    /**
     * Process callbacks from YAML templates
     */
    protected function processCallbacks(array $context): array
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
    protected function processSteps(array $context): array
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
            
            $handlerName = $stepConfig['handler'] ?? 'form';
            
            // Check if handler is available
            if (!$this->isHandlerAvailable($handlerName)) {
                // Create fallback step for missing handler
                $step = $this->createMissingHandlerStep(
                    $handlerName,
                    $stepConfig['title'] ?? 'Unknown Step',
                    $stepConfig
                );
                $steps[] = $step;
                continue;
            }
            
            // Process step configuration
            $step = [
                'handler' => $handlerName,
                'config' => [],
            ];
            
            // Add step_name if present (for named references)
            if (isset($stepConfig['step_name'])) {
                $step['config']['step_name'] = $stepConfig['step_name'];
            }
            
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
    
    /**
     * Check if handler is available
     */
    protected function isHandlerAvailable(string $handlerName): bool
    {
        $handlerClass = $this->getHandlerClass($handlerName);
        return $handlerClass && class_exists($handlerClass);
    }
    
    /**
     * Get handler class from name
     */
    protected function getHandlerClass(string $handlerName): ?string
    {
        // Reuse FormFlowController's logic
        $configHandlers = config('form-flow.handlers', []);
        $builtInHandlers = [
            'form' => \LBHurtado\FormFlowManager\Handlers\FormHandler::class,
            'missing' => \LBHurtado\FormFlowManager\Handlers\MissingHandler::class,
        ];
        $handlers = array_merge($builtInHandlers, $configHandlers);
        return $handlers[$handlerName] ?? null;
    }
    
    /**
     * Create fallback step for missing handler
     */
    protected function createMissingHandlerStep(
        string $handlerName,
        string $title,
        array $originalConfig
    ): array {
        $config = [
            'missing_handler_name' => $handlerName,
            'missing_handler_title' => $title,
            'original_config' => $originalConfig,
            'install_hint' => $this->getInstallHint($handlerName),
        ];
        
        // Preserve step_name if present
        if (isset($originalConfig['step_name'])) {
            $config['step_name'] = $originalConfig['step_name'];
        }
        
        return [
            'handler' => 'missing',
            'config' => $config,
        ];
    }
    
    /**
     * Get installation hint for handler
     */
    protected function getInstallHint(string $handlerName): string
    {
        $packageMap = [
            'kyc' => 'lbhurtado/form-handler-kyc',
            'location' => 'lbhurtado/form-handler-location',
            'otp' => 'lbhurtado/form-handler-otp',
            'signature' => 'lbhurtado/form-handler-signature',
            'selfie' => 'lbhurtado/form-handler-selfie',
        ];
        
        $package = $packageMap[$handlerName] ?? "lbhurtado/form-handler-{$handlerName}";
        return "composer require {$package}";
    }
}
