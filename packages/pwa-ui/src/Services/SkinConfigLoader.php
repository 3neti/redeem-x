<?php

namespace LBHurtado\PwaUi\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class SkinConfigLoader
{
    /**
     * Valid voucher types.
     */
    protected const VALID_VOUCHER_TYPES = ['settlement', 'redeemable', 'payable'];

    /**
     * Load a skin configuration from YAML file.
     *
     * Supports:
     * - Automatic placeholder generation (label → "Enter <label>")
     * - Query parameter overrides
     * - Fallback to defaults
     *
     * @param string $skinName Skin identifier (e.g., 'philhealth-bst')
     * @param array $queryParams URL query parameters for overrides
     * @return array|null Processed skin config or null if not found
     */
    public function load(string $skinName, array $queryParams = []): ?array
    {
        $yamlPath = $this->resolveSkinPath($skinName);

        if (!$yamlPath || !file_exists($yamlPath)) {
            return null;
        }

        $config = Yaml::parseFile($yamlPath);

        // Process the config
        $processed = $this->processConfig($config, $queryParams);

        return $processed;
    }

    /**
     * Resolve the path to a skin's YAML file.
     *
     * Search order:
     * 1. Published location: config/pwa-skins/{skin}.yaml
     * 2. Package location: packages/pwa-ui/resources/skins/{skin}/kiosk.yaml
     *
     * @param string $skinName
     * @return string|null
     */
    protected function resolveSkinPath(string $skinName): ?string
    {
        // Try published location first
        $publishedPath = config_path("pwa-skins/{$skinName}.yaml");
        if (file_exists($publishedPath)) {
            return $publishedPath;
        }

        // Try package location
        $packagePath = base_path("packages/pwa-ui/resources/skins/{$skinName}/kiosk.yaml");
        if (file_exists($packagePath)) {
            return $packagePath;
        }

        return null;
    }

    /**
     * Process the raw YAML config.
     *
     * - Apply query parameter overrides
     * - Generate automatic placeholders
     * - Flatten UI config for easier frontend consumption
     *
     * @param array $config Raw YAML config
     * @param array $queryParams Query parameters
     * @return array Processed config
     */
    protected function processConfig(array $config, array $queryParams): array
    {
        // Start with base config
        $processed = [
            'title' => $this->override($config['title'] ?? 'Quick Voucher', $queryParams, 'title'),
            'subtitle' => $this->override($config['subtitle'] ?? null, $queryParams, 'subtitle'),
            'card_description' => $this->override($config['card_description'] ?? null, $queryParams, 'card_description'),
            'voucher_type' => $this->validateVoucherType(
                $this->override($config['voucher_type'] ?? 'settlement', $queryParams, 'type')
            ),
            'campaign' => $this->override($config['config']['campaign'] ?? null, $queryParams, 'campaign'),
            'driver' => $this->override($config['config']['driver'] ?? null, $queryParams, 'driver'),
            'amount' => $this->override($config['config']['amount'] ?? null, $queryParams, 'amount'),
            'target_amount' => $this->override($config['config']['target_amount'] ?? null, $queryParams, 'target_amount'),
            'inputs' => $this->parseCommaSeparated($queryParams['inputs'] ?? null) ?? ($config['fields']['inputs'] ?? []),
            'payload' => $this->processPayloadFields($config['fields']['payload'] ?? []),
            'feedback' => $this->override($config['callbacks']['feedback'] ?? null, $queryParams, 'feedback'),
        ];

        // Process UI labels with automatic placeholders
        $ui = $config['ui'] ?? [];
        $processed['ui'] = [
            'logo' => $ui['logo'] ?? null,
            'theme_color' => $ui['theme_color'] ?? '#0066cc',
            
            // Type label
            'type_label' => $this->override($ui['type_label'] ?? null, $queryParams, 'type_label'),
            
            // Amount field
            'amount_label' => $this->override($ui['amount_label'] ?? 'Amount', $queryParams, 'amount_label'),
            'amount_placeholder' => $this->override(
                $ui['amount_placeholder'] ?? $this->generatePlaceholder($ui['amount_label'] ?? 'Amount'),
                $queryParams,
                'amount_placeholder'
            ),
            'amount_keypad_title' => $this->override(
                $ui['amount_keypad_title'] ?? $this->generateKeypadTitle($ui['amount_label'] ?? 'Amount'),
                $queryParams,
                'amount_keypad_title'
            ),
            
            // Target field
            'target_label' => $this->override($ui['target_label'] ?? 'Target Amount', $queryParams, 'target_label'),
            'target_placeholder' => $this->override(
                $ui['target_placeholder'] ?? $this->generatePlaceholder($ui['target_label'] ?? 'Target Amount'),
                $queryParams,
                'target_placeholder'
            ),
            'target_keypad_title' => $this->override(
                $ui['target_keypad_title'] ?? $this->generateKeypadTitle($ui['target_label'] ?? 'Target Amount'),
                $queryParams,
                'target_keypad_title'
            ),
            
            // Buttons
            'button_text' => $this->override($ui['button_text'] ?? 'Issue Voucher', $queryParams, 'button_text'),
            'print_button' => $this->override($ui['print_button'] ?? 'Print', $queryParams, 'print_button'),
            'new_button' => $this->override($ui['new_button'] ?? 'Issue Another', $queryParams, 'new_button'),
            'retry_button' => $this->override($ui['retry_button'] ?? 'Try Again', $queryParams, 'retry_button'),
            
            // Success state
            'success_title' => $this->override($ui['success_title'] ?? 'Voucher Issued!', $queryParams, 'success_title'),
            'success_message' => $this->override($ui['success_message'] ?? 'Scan QR code to redeem', $queryParams, 'success_message'),
            
            // Error state
            'error_title' => $this->override($ui['error_title'] ?? 'Error', $queryParams, 'error_title'),
        ];

        return $processed;
    }

    /**
     * Generate automatic placeholder from label.
     *
     * Examples:
     * - "Amount" → "Enter amount"
     * - "Reimbursement Amount" → "Enter reimbursement amount"
     * - "BST Code" → "Enter BST code"
     *
     * @param string $label
     * @return string
     */
    protected function generatePlaceholder(string $label): string
    {
        return 'Enter ' . Str::lower($label);
    }

    /**
     * Generate automatic keypad title from label.
     *
     * Uses the label directly (not "Enter [label]") since keypads
     * are self-explanatory input interfaces.
     *
     * Examples:
     * - "Amount" → "Amount"
     * - "Reimbursement Amount" → "Reimbursement Amount"
     * - "Case Rate" → "Case Rate"
     *
     * @param string $label
     * @return string
     */
    protected function generateKeypadTitle(string $label): string
    {
        return $label;
    }

    /**
     * Apply query parameter override if present.
     *
     * @param mixed $default Default value from YAML
     * @param array $queryParams Query parameters
     * @param string $key Parameter key
     * @return mixed
     */
    protected function override($default, array $queryParams, string $key)
    {
        return $queryParams[$key] ?? $default;
    }

    /**
     * Process payload fields configuration.
     *
     * Supports both string and object formats:
     * - String format (backward compatible): ['reference', 'device']
     * - Object format (new): [['name' => 'device', 'type' => 'auto_device_id', ...]]
     *
     * @param array $payloadFields Raw payload fields from YAML
     * @return array Processed payload fields
     */
    protected function processPayloadFields(array $payloadFields): array
    {
        $processed = [];

        foreach ($payloadFields as $field) {
            // Backward compatibility: string format
            if (is_string($field)) {
                $processed[] = [
                    'name' => $field,
                    'type' => 'text',
                    'editable' => true,
                    'required' => true,
                ];
                continue;
            }

            // New object format
            if (is_array($field) && isset($field['name'])) {
                $processed[] = [
                    'name' => $field['name'],
                    'type' => $field['type'] ?? 'text',
                    'editable' => $field['editable'] ?? true,
                    'required' => $field['required'] ?? false,
                    'placeholder' => $field['placeholder'] ?? null,
                ];
            }
        }

        return $processed;
    }

    /**
     * Validate voucher type.
     *
     * @param string $type Voucher type to validate
     * @return string Validated voucher type
     * @throws \InvalidArgumentException If voucher type is invalid
     */
    protected function validateVoucherType(string $type): string
    {
        if (!in_array($type, self::VALID_VOUCHER_TYPES, true)) {
            $validTypes = implode(', ', self::VALID_VOUCHER_TYPES);
            
            throw new \InvalidArgumentException(
                "Invalid voucher_type \"{$type}\" in skin configuration.\n" .
                "Allowed values: {$validTypes}\n\n" .
                "Did you mean to use type_label instead? The voucher_type field defines the voucher\n" .
                "behavior (settlement|redeemable|payable), while type_label defines the display name.\n\n" .
                "Example:\n" .
                "  voucher_type: settlement  # System type\n" .
                "  ui:\n" .
                "    type_label: BST         # Display label"
            );
        }

        return $type;
    }

    /**
     * Parse comma-separated string into array.
     *
     * @param string|null $value
     * @return array|null
     */
    protected function parseCommaSeparated(?string $value): ?array
    {
        if (!$value) {
            return null;
        }

        return array_map('trim', explode(',', $value));
    }
}
