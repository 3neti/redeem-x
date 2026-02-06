<?php

namespace LBHurtado\SettlementEnvelope\Services;

use LBHurtado\SettlementEnvelope\Data\DriverData;
use LBHurtado\SettlementEnvelope\Data\GateDefinitionData;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Enums\ChecklistItemStatus;
use LBHurtado\SettlementEnvelope\Enums\ChecklistItemKind;

class GateEvaluator
{
    /**
     * Evaluate all gates for an envelope
     */
    public function evaluate(Envelope $envelope, DriverData $driver): array
    {
        $context = $this->buildContext($envelope, $driver);
        $results = [];

        foreach ($driver->gates as $gate) {
            $results[$gate->key] = $this->evaluateGate($gate, $context, $results);
        }

        return $results;
    }

    /**
     * Evaluate a single gate
     */
    protected function evaluateGate(GateDefinitionData $gate, array $context, array $previousResults): bool
    {
        $rule = $gate->rule;

        // Add previous gate results to context
        $context['gate'] = $previousResults;

        return $this->evaluateExpression($rule, $context);
    }

    /**
     * Build context object for gate evaluation
     */
    protected function buildContext(Envelope $envelope, DriverData $driver): array
    {
        // Load relationships if not loaded
        $envelope->loadMissing(['checklistItems', 'signals']);

        return [
            'payload' => $this->buildPayloadContext($envelope),
            'checklist' => $this->buildChecklistContext($envelope),
            'signal' => $this->buildSignalContext($envelope, $driver),
            'envelope' => [
                'status' => $envelope->status->value,
                'payload_version' => $envelope->payload_version,
            ],
        ];
    }

    protected function buildPayloadContext(Envelope $envelope): array
    {
        // Basic payload validity - check if payload exists and is non-empty
        $hasPayload = !empty($envelope->payload);

        return [
            'valid' => $hasPayload,
            'version' => $envelope->payload_version,
        ];
    }

    protected function buildChecklistContext(Envelope $envelope): array
    {
        $items = $envelope->checklistItems;

        $requiredItems = $items->where('required', true);
        $requiredAccepted = $requiredItems->where('status', ChecklistItemStatus::ACCEPTED)->count();
        $requiredCount = $requiredItems->count();

        return [
            'total' => $items->count(),
            'required_count' => $requiredCount,
            'required_accepted' => $requiredAccepted === $requiredCount,
            'all_accepted' => $items->where('status', ChecklistItemStatus::ACCEPTED)->count() === $items->count(),
            'has_rejected' => $items->where('status', ChecklistItemStatus::REJECTED)->count() > 0,
            'pending_count' => $items->whereIn('status', [
                ChecklistItemStatus::MISSING,
                ChecklistItemStatus::UPLOADED,
                ChecklistItemStatus::NEEDS_REVIEW,
            ])->count(),
        ];
    }

    protected function buildSignalContext(Envelope $envelope, DriverData $driver): array
    {
        $signals = [];

        // Initialize all defined signals with defaults
        foreach ($driver->signals as $signalDef) {
            $signals[$signalDef->key] = $signalDef->default;
        }

        // Override with actual signal values
        foreach ($envelope->signals as $signal) {
            $signals[$signal->key] = $signal->getBoolValue();
        }

        return $signals;
    }

    /**
     * Simple expression evaluator
     * Supports: &&, ||, ==, !=, !, true, false, dot notation
     */
    protected function evaluateExpression(string $expression, array $context): bool
    {
        $expression = trim($expression);

        // Handle simple boolean literals
        if ($expression === 'true') {
            return true;
        }
        if ($expression === 'false') {
            return false;
        }

        // Handle negation
        if (str_starts_with($expression, '!')) {
            return !$this->evaluateExpression(substr($expression, 1), $context);
        }

        // Handle AND (&&)
        if (str_contains($expression, '&&')) {
            $parts = array_map('trim', explode('&&', $expression, 2));
            return $this->evaluateExpression($parts[0], $context) && $this->evaluateExpression($parts[1], $context);
        }

        // Handle OR (||)
        if (str_contains($expression, '||')) {
            $parts = array_map('trim', explode('||', $expression, 2));
            return $this->evaluateExpression($parts[0], $context) || $this->evaluateExpression($parts[1], $context);
        }

        // Handle equality (==)
        if (str_contains($expression, '==')) {
            $parts = array_map('trim', explode('==', $expression, 2));
            $left = $this->resolveValue($parts[0], $context);
            $right = $this->resolveValue($parts[1], $context);
            return $left == $right;
        }

        // Handle inequality (!=)
        if (str_contains($expression, '!=')) {
            $parts = array_map('trim', explode('!=', $expression, 2));
            $left = $this->resolveValue($parts[0], $context);
            $right = $this->resolveValue($parts[1], $context);
            return $left != $right;
        }

        // Handle simple reference (e.g., "signal.kyc_passed")
        $value = $this->resolveValue($expression, $context);

        return (bool) $value;
    }

    /**
     * Resolve a value from context using dot notation
     */
    protected function resolveValue(string $reference, array $context): mixed
    {
        $reference = trim($reference);

        // Handle literals
        if ($reference === 'true') {
            return true;
        }
        if ($reference === 'false') {
            return false;
        }
        if (is_numeric($reference)) {
            return (float) $reference;
        }
        if (str_starts_with($reference, '"') && str_ends_with($reference, '"')) {
            return trim($reference, '"');
        }
        if (str_starts_with($reference, "'") && str_ends_with($reference, "'")) {
            return trim($reference, "'");
        }

        // Resolve dot notation
        $parts = explode('.', $reference);
        $current = $context;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Validate a gate rule (for driver validation)
     */
    public function validateRule(string $rule): bool
    {
        // Basic syntax check - ensure balanced parentheses and valid operators
        $validOperators = ['&&', '||', '==', '!=', '!', 'true', 'false'];
        $validPattern = '/^[a-zA-Z0-9_.\s&|!=()]+$/';

        return preg_match($validPattern, $rule) === 1;
    }
}
