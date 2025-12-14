<?php

namespace LBHurtado\FormFlowManager\Services;

class TemplateProcessor
{
    /**
     * Process a template string with the given context.
     */
    public function process(string $template, array $context): string
    {
        // Match all {{ ... }} patterns
        return preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', function ($matches) use ($context) {
            $expression = trim($matches[1]);
            
            // Check if it's a conditional (contains ?)
            if (str_contains($expression, '?')) {
                return $this->evaluateConditional($expression, $context);
            }
            
            // Check if it contains a filter (contains |)
            if (str_contains($expression, '|')) {
                return $this->applyFilterPipeline($expression, $context);
            }
            
            // Boolean expression with logical operators
            if (preg_match('/\s(?:or|and)\s/i', $expression)) {
                return $this->evaluateBooleanExpression($expression, $context) ? '1' : '';
            }
            
            // Simple variable resolution
            return $this->resolveVariable($expression, $context);
        }, $template);
    }

    /**
     * Process an entire array/object structure recursively.
     */
    public function processArray(array $data, array $context): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $result[$key] = $this->process($value, $context);
            } elseif (is_array($value)) {
                $result[$key] = $this->processArray($value, $context);
            } else {
                // Preserve non-string, non-array values (bool, int, null, etc.)
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Resolve a variable path from context using dot notation.
     */
    public function resolveVariable(string $path, array $context): string
    {
        $value = $this->resolveVariableRaw($path, $context);
        
        // Convert to string, handle null/empty
        if ($value === null) {
            return '';
        }
        
        if (is_array($value)) {
            return 'Array'; // Default string representation
        }
        
        return (string) $value;
    }

    /**
     * Resolve a variable path from context and return the raw value.
     */
    protected function resolveVariableRaw(string $path, array $context): mixed
    {
        $value = $context;
        
        foreach (explode('.', $path) as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null; // Variable not found
            }
        }
        
        return $value;
    }

    /**
     * Apply filter pipeline (variable | filter1 | filter2).
     */
    protected function applyFilterPipeline(string $expression, array $context): string
    {
        $parts = explode('|', $expression);
        $variablePath = trim(array_shift($parts));
        
        // Resolve the initial variable (get raw value for filters)
        $value = $this->resolveVariableRaw($variablePath, $context);
        
        // Apply filters in sequence
        foreach ($parts as $filterName) {
            $filterName = trim($filterName);
            $value = $this->applyFilter($value, $filterName);
        }
        
        // Convert final value to string if not already
        return is_string($value) ? $value : (string) $value;
    }

    /**
     * Apply a single filter to a value.
     */
    public function applyFilter(mixed $value, string $filter): mixed
    {
        return match ($filter) {
            'upper' => strtoupper((string) $value),
            'lower' => strtolower((string) $value),
            'format_money' => $this->formatMoney($value),
            'json' => $this->formatJson($value),
            default => $value,
        };
    }

    /**
     * Evaluate a ternary conditional expression.
     */
    public function evaluateConditional(string $expression, array $context): string
    {
        // Parse ternary: condition ? true_value : false_value
        if (!preg_match('/^(.+?)\s*\?\s*"([^"]*)"\s*:\s*"([^"]*)"$/', $expression, $matches)) {
            return ''; // Invalid conditional format
        }
        
        $condition = trim($matches[1]);
        $trueValue = $matches[2];
        $falseValue = $matches[3];
        
        $result = $this->evaluateCondition($condition, $context);
        
        return $result ? $trueValue : $falseValue;
    }

    /**
     * Evaluate a boolean condition.
     */
    protected function evaluateCondition(string $condition, array $context): bool
    {
        // Handle equality (==)
        if (preg_match('/^(.+?)\s*==\s*(.+?)$/', $condition, $matches)) {
            $left = $this->resolveValue(trim($matches[1]), $context);
            $right = $this->resolveValue(trim($matches[2]), $context);
            return $left == $right;
        }
        
        // Handle inequality (!=)
        if (preg_match('/^(.+?)\s*!=\s*(.+?)$/', $condition, $matches)) {
            $left = $this->resolveValue(trim($matches[1]), $context);
            $right = $this->resolveValue(trim($matches[2]), $context);
            return $left != $right;
        }
        
        // Handle greater than (>)
        if (preg_match('/^(.+?)\s*>\s*(.+?)$/', $condition, $matches)) {
            $left = $this->resolveValue(trim($matches[1]), $context);
            $right = $this->resolveValue(trim($matches[2]), $context);
            return $left > $right;
        }
        
        // Handle less than (<)
        if (preg_match('/^(.+?)\s*<\s*(.+?)$/', $condition, $matches)) {
            $left = $this->resolveValue(trim($matches[1]), $context);
            $right = $this->resolveValue(trim($matches[2]), $context);
            return $left < $right;
        }
        
        // Handle greater than or equal (>=)
        if (preg_match('/^(.+?)\s*>=\s*(.+?)$/', $condition, $matches)) {
            $left = $this->resolveValue(trim($matches[1]), $context);
            $right = $this->resolveValue(trim($matches[2]), $context);
            return $left >= $right;
        }
        
        // Handle less than or equal (<=)
        if (preg_match('/^(.+?)\s*<=\s*(.+?)$/', $condition, $matches)) {
            $left = $this->resolveValue(trim($matches[1]), $context);
            $right = $this->resolveValue(trim($matches[2]), $context);
            return $left <= $right;
        }
        
        // Simple boolean variable or boolean expression
        if (preg_match('/\s(?:or|and)\s/i', $condition)) {
            return $this->evaluateBooleanExpression($condition, $context);
        }
        
        $value = $this->resolveValue($condition, $context);
        return (bool) $value;
    }

    /**
     * Resolve a value (variable or literal) for condition evaluation.
     */
    protected function resolveValue(string $value, array $context): mixed
    {
        // Check if it's a numeric literal
        if (is_numeric($value)) {
            return $value + 0; // Convert to int or float
        }
        
        // Check if it's a string literal (quoted)
        if (preg_match('/^["\'](.+)["\']$/', $value, $matches)) {
            return $matches[1];
        }
        
        // Check if it's a boolean literal
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        
        // Resolve as variable path
        $resolved = $this->resolveVariable($value, $context);
        
        // Try to convert to appropriate type
        if (is_numeric($resolved)) {
            return $resolved + 0;
        }
        
        if ($resolved === 'true') {
            return true;
        }
        if ($resolved === 'false') {
            return false;
        }
        
        return $resolved;
    }

    /**
     * Evaluate boolean expressions with 'or' / 'and'.
     */
    protected function evaluateBooleanExpression(string $expr, array $context): bool
    {
        // Normalize whitespace and lowercase operators
        $expression = preg_replace('/\s+/', ' ', trim($expr));
        
        // Split by ' or '
        $orParts = preg_split('/\sor\s/i', $expression);
        $result = false;
        foreach ($orParts as $orPart) {
            // Each OR part can contain ANDs
            $andTokens = preg_split('/\sand\s/i', trim($orPart));
            $andResult = true;
            foreach ($andTokens as $token) {
                $token = trim($token);
                // Allow negation with leading '!'
                $negated = false;
                if (str_starts_with($token, '!')) {
                    $negated = true;
                    $token = ltrim($token, '!');
                }
                $value = (bool) $this->resolveValue($token, $context);
                $andResult = $andResult && ($negated ? !$value : $value);
                if (!$andResult) {
                    break; // Short-circuit AND
                }
            }
            $result = $result || $andResult;
            if ($result) {
                break; // Short-circuit OR
            }
        }
        return $result;
    }

    /**
     * Format a value as money (PHP currency).
     */
    protected function formatMoney(mixed $value): string
    {
        $numericValue = is_numeric($value) ? $value + 0 : 0;
        return '₱' . number_format($numericValue, 2);
    }

    /**
     * Format a value as JSON.
     */
    protected function formatJson(mixed $value): string
    {
        // If already a string and valid JSON, return it
        if (is_string($value) && json_decode($value, true) !== null) {
            return $value;
        }
        
        // Otherwise, encode the value as JSON
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
