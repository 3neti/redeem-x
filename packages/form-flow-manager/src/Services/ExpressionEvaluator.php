<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Services;

use Illuminate\Support\Str;

/**
 * Expression Evaluator
 * 
 * Evaluates boolean expressions and conditions for filtering and validation.
 * Supports:
 * - Comparison operators: ==, !=, >, <, >=, <=
 * - Logical operators: &&, ||, !
 * - in operator: {{ item in ['selfie', 'signature'] }}
 * - empty() function
 * - Function calls via TemplateRenderer
 */
class ExpressionEvaluator
{
    public function __construct(
        protected TemplateRenderer $renderer
    ) {}
    
    /**
     * Evaluate a boolean expression
     * 
     * @param string $expression Expression to evaluate
     * @param mixed $context Context data (can be a single item or full context array)
     * @return bool Evaluation result
     */
    public function evaluate(string $expression, mixed $context): bool
    {
        // Normalize context to array
        if (!is_array($context)) {
            $context = ['item' => $context];
        }
        
        // Handle logical OR (||)
        if (Str::contains($expression, '||')) {
            return $this->evaluateOr($expression, $context);
        }
        
        // Handle logical AND (&&)
        if (Str::contains($expression, '&&')) {
            return $this->evaluateAnd($expression, $context);
        }
        
        // Handle logical NOT (!)
        if (Str::startsWith(trim($expression), '!')) {
            return !$this->evaluate(trim(substr($expression, 1)), $context);
        }
        
        // Handle 'in' operator
        if (preg_match('/(.+)\s+in\s+(.+)/i', $expression, $matches)) {
            return $this->evaluateIn($matches[1], $matches[2], $context);
        }
        
        // Handle comparison operators
        if (preg_match('/(.+)\s*(==|!=|>=|<=|>|<)\s*(.+)/', $expression, $matches)) {
            return $this->evaluateComparison($matches[1], $matches[2], $matches[3], $context);
        }
        
        // Handle empty() function
        if (preg_match('/empty\((.+)\)/', $expression, $matches)) {
            return $this->evaluateEmpty($matches[1], $context);
        }
        
        // Handle function calls (config, env, etc.)
        if (Str::contains($expression, '(') && Str::contains($expression, ')')) {
            $result = $this->renderer->render("{{ {$expression} }}", $context);
            return $this->toBoolean($result);
        }
        
        // Simple boolean value
        $trimmed = trim($expression);
        if ($trimmed === 'true') return true;
        if ($trimmed === 'false') return false;
        
        // Evaluate as template and convert to boolean
        $result = $this->renderer->render("{{ {$expression} }}", $context);
        return $this->toBoolean($result);
    }
    
    /**
     * Evaluate logical OR expression
     * 
     * @param string $expression Expression with ||
     * @param array $context Context data
     * @return bool Result
     */
    protected function evaluateOr(string $expression, array $context): bool
    {
        $parts = $this->splitByOperator($expression, '||');
        
        foreach ($parts as $part) {
            if ($this->evaluate($part, $context)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Evaluate logical AND expression
     * 
     * @param string $expression Expression with &&
     * @param array $context Context data
     * @return bool Result
     */
    protected function evaluateAnd(string $expression, array $context): bool
    {
        $parts = $this->splitByOperator($expression, '&&');
        
        foreach ($parts as $part) {
            if (!$this->evaluate($part, $context)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Evaluate 'in' operator
     * 
     * @param string $needle Value to search for
     * @param string $haystack Array to search in
     * @param array $context Context data
     * @return bool Result
     */
    protected function evaluateIn(string $needle, string $haystack, array $context): bool
    {
        // Resolve needle value
        $needleValue = $this->renderer->render("{{ {$needle} }}", $context);
        
        // Parse haystack as array
        $haystackValue = $this->parseArray($haystack, $context);
        
        if (!is_array($haystackValue)) {
            return false;
        }
        
        return in_array($needleValue, $haystackValue, true);
    }
    
    /**
     * Evaluate comparison expression
     * 
     * @param string $left Left operand
     * @param string $operator Comparison operator
     * @param string $right Right operand
     * @param array $context Context data
     * @return bool Result
     */
    protected function evaluateComparison(string $left, string $operator, string $right, array $context): bool
    {
        $leftValue = $this->resolveValue(trim($left), $context);
        $rightValue = $this->resolveValue(trim($right), $context);
        
        return match($operator) {
            '==' => $leftValue == $rightValue,
            '!=' => $leftValue != $rightValue,
            '>' => $leftValue > $rightValue,
            '<' => $leftValue < $rightValue,
            '>=' => $leftValue >= $rightValue,
            '<=' => $leftValue <= $rightValue,
            default => false,
        };
    }
    
    /**
     * Evaluate empty() function
     * 
     * @param string $expression Expression inside empty()
     * @param array $context Context data
     * @return bool Result
     */
    protected function evaluateEmpty(string $expression, array $context): bool
    {
        $value = $this->resolveValue(trim($expression), $context);
        
        if (is_string($value)) {
            // Template might return 'true'/'false' strings
            if ($value === 'true') return false;
            if ($value === 'false') return true;
        }
        
        return empty($value);
    }
    
    /**
     * Resolve a value from expression
     * 
     * @param string $expression Expression to resolve
     * @param array $context Context data
     * @return mixed Resolved value
     */
    protected function resolveValue(string $expression, array $context): mixed
    {
        // String literal
        if ((Str::startsWith($expression, "'") && Str::endsWith($expression, "'")) ||
            (Str::startsWith($expression, '"') && Str::endsWith($expression, '"'))) {
            return trim($expression, "\"'");
        }
        
        // Numeric
        if (is_numeric($expression)) {
            return str_contains($expression, '.') ? (float) $expression : (int) $expression;
        }
        
        // Boolean
        if ($expression === 'true') return true;
        if ($expression === 'false') return false;
        if ($expression === 'null') return null;
        
        // Array literal
        if (Str::startsWith($expression, '[') && Str::endsWith($expression, ']')) {
            return $this->parseArray($expression, $context);
        }
        
        // Render as template
        $result = $this->renderer->render("{{ {$expression} }}", $context);
        
        // Try to parse as number
        if (is_numeric($result)) {
            return str_contains($result, '.') ? (float) $result : (int) $result;
        }
        
        // Try to parse as boolean
        if ($result === 'true') return true;
        if ($result === 'false') return false;
        
        return $result;
    }
    
    /**
     * Parse array literal
     * 
     * @param string $arrayString Array literal string
     * @param array $context Context data
     * @return array Parsed array
     */
    protected function parseArray(string $arrayString, array $context): array
    {
        $content = trim($arrayString, '[]');
        if ($content === '') {
            return [];
        }
        
        // Split by comma (respecting quotes)
        $items = $this->splitByComma($content);
        
        $result = [];
        foreach ($items as $item) {
            $item = trim($item);
            
            // String literal
            if ((Str::startsWith($item, "'") && Str::endsWith($item, "'")) ||
                (Str::startsWith($item, '"') && Str::endsWith($item, '"'))) {
                $result[] = trim($item, "\"'");
            } else {
                // Resolve as expression
                $result[] = $this->resolveValue($item, $context);
            }
        }
        
        return $result;
    }
    
    /**
     * Split expression by operator (respecting parentheses and quotes)
     * 
     * @param string $expression Expression to split
     * @param string $operator Operator to split by
     * @return array Parts
     */
    protected function splitByOperator(string $expression, string $operator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = null;
        $operatorLength = strlen($operator);
        
        for ($i = 0; $i < strlen($expression); $i++) {
            $char = $expression[$i];
            
            // Track string boundaries
            if (($char === '"' || $char === "'") && ($i === 0 || $expression[$i - 1] !== '\\')) {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                    $stringChar = null;
                }
                $current .= $char;
                continue;
            }
            
            // Track depth for parentheses
            if (!$inString) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }
                
                // Check for operator at depth 0
                if ($depth === 0 && substr($expression, $i, $operatorLength) === $operator) {
                    $parts[] = trim($current);
                    $current = '';
                    $i += $operatorLength - 1; // Skip operator
                    continue;
                }
            }
            
            $current .= $char;
        }
        
        if ($current !== '') {
            $parts[] = trim($current);
        }
        
        return $parts;
    }
    
    /**
     * Split string by comma (respecting quotes)
     * 
     * @param string $string String to split
     * @return array Parts
     */
    protected function splitByComma(string $string): array
    {
        $parts = [];
        $current = '';
        $inString = false;
        $stringChar = null;
        
        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            
            // Track string boundaries
            if (($char === '"' || $char === "'") && ($i === 0 || $string[$i - 1] !== '\\')) {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                    $stringChar = null;
                }
                $current .= $char;
                continue;
            }
            
            // Split on comma outside strings
            if (!$inString && $char === ',') {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if ($current !== '') {
            $parts[] = trim($current);
        }
        
        return $parts;
    }
    
    /**
     * Convert value to boolean
     * 
     * @param mixed $value Value to convert
     * @return bool Boolean result
     */
    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if ($lower === 'true' || $lower === '1') return true;
            if ($lower === 'false' || $lower === '0' || $lower === '') return false;
        }
        
        return (bool) $value;
    }
}
