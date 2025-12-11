<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Services;

use Illuminate\Support\Str;

/**
 * Template Renderer
 * 
 * Renders templates with {{ }} syntax for variable interpolation.
 * Supports:
 * - Simple variables: {{ variable }}
 * - Dot notation: {{ source.code }}
 * - Function calls: {{ route('api.redeem', {voucher: source.code}) }}
 * - Null coalescing: {{ source.owner.name ?? 'Unknown' }}
 * - Concatenation: {{ 'prefix_' ~ source.code }}
 */
class TemplateRenderer
{
    /**
     * Render a template string with context data
     * 
     * @param string $template Template string with {{ }} placeholders
     * @param array $context Context data for variable resolution
     * @return string Rendered template
     */
    public function render(string $template, array $context): string
    {
        // Handle templates without placeholders
        if (!Str::contains($template, '{{')) {
            return $template;
        }
        
        return preg_replace_callback(
            '/\{\{\s*([^}]+)\s*\}\}/',
            fn($matches) => $this->evaluateExpression(trim($matches[1]), $context),
            $template
        );
    }
    
    /**
     * Evaluate a template expression
     * 
     * @param string $expression Expression inside {{ }}
     * @param array $context Context data
     * @return string Evaluated result
     */
    protected function evaluateExpression(string $expression, array $context): string
    {
        // Handle null coalescing: {{ source.owner.name ?? 'Unknown' }}
        if (Str::contains($expression, '??')) {
            return $this->evaluateNullCoalescing($expression, $context);
        }
        
        // Handle concatenation: {{ 'prefix_' ~ source.code }}
        if (Str::contains($expression, '~')) {
            return $this->evaluateConcatenation($expression, $context);
        }
        
        // Handle function calls: {{ route('api.redeem', ...) }}
        if (Str::contains($expression, '(') && Str::contains($expression, ')')) {
            return $this->evaluateFunction($expression, $context);
        }
        
        // Handle dot notation: {{ source.code }}
        if (Str::contains($expression, '.')) {
            return $this->evaluateDotNotation($expression, $context);
        }
        
        // Simple variable: {{ variable }}
        $value = $context[$expression] ?? null;
        
        if (is_null($value)) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }
    
    /**
     * Evaluate null coalescing operator
     * 
     * @param string $expression Expression with ??
     * @param array $context Context data
     * @return string Result
     */
    protected function evaluateNullCoalescing(string $expression, array $context): string
    {
        [$left, $right] = array_map('trim', explode('??', $expression, 2));
        
        $leftValue = $this->evaluateExpression($left, $context);
        
        if ($leftValue !== '' && $leftValue !== 'null') {
            return $leftValue;
        }
        
        // Right side might be a literal string
        if (Str::startsWith($right, "'") && Str::endsWith($right, "'")) {
            return trim($right, "'");
        }
        
        if (Str::startsWith($right, '"') && Str::endsWith($right, '"')) {
            return trim($right, '"');
        }
        
        return $this->evaluateExpression($right, $context);
    }
    
    /**
     * Evaluate concatenation operator (~)
     * 
     * @param string $expression Expression with ~
     * @param array $context Context data
     * @return string Concatenated result
     */
    protected function evaluateConcatenation(string $expression, array $context): string
    {
        $parts = array_map('trim', explode('~', $expression));
        $result = '';
        
        foreach ($parts as $part) {
            // Literal string
            if ((Str::startsWith($part, "'") && Str::endsWith($part, "'")) ||
                (Str::startsWith($part, '"') && Str::endsWith($part, '"'))) {
                $result .= trim($part, "\"'");
            } else {
                // Expression
                $result .= $this->evaluateExpression($part, $context);
            }
        }
        
        return $result;
    }
    
    /**
     * Evaluate function call
     * 
     * @param string $expression Function call expression
     * @param array $context Context data
     * @return string Result
     */
    protected function evaluateFunction(string $expression, array $context): string
    {
        // Extract function name and arguments
        if (!preg_match('/^(\w+)\((.*)\)$/', $expression, $matches)) {
            return '';
        }
        
        $functionName = $matches[1];
        $argsString = $matches[2];
        
        // Parse arguments
        $args = $this->parseArguments($argsString, $context);
        
        // Execute function if it exists in context
        if (isset($context[$functionName]) && is_callable($context[$functionName])) {
            try {
                $result = call_user_func_array($context[$functionName], $args);
                return (string) $result;
            } catch (\Throwable $e) {
                // Log error but don't break rendering
                return '';
            }
        }
        
        // Try as global function
        if (function_exists($functionName)) {
            try {
                $result = call_user_func_array($functionName, $args);
                return (string) $result;
            } catch (\Throwable $e) {
                return '';
            }
        }
        
        return '';
    }
    
    /**
     * Parse function arguments
     * 
     * @param string $argsString Arguments string
     * @param array $context Context data
     * @return array Parsed arguments
     */
    protected function parseArguments(string $argsString, array $context): array
    {
        if (trim($argsString) === '') {
            return [];
        }
        
        // Simple argument parsing (handles strings, variables, arrays)
        $args = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = null;
        
        for ($i = 0; $i < strlen($argsString); $i++) {
            $char = $argsString[$i];
            
            // Track string boundaries
            if (($char === '"' || $char === "'") && ($i === 0 || $argsString[$i - 1] !== '\\')) {
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
            
            // Track depth for nested structures
            if (!$inString) {
                if ($char === '{' || $char === '[') {
                    $depth++;
                } elseif ($char === '}' || $char === ']') {
                    $depth--;
                }
                
                // Split on comma at depth 0
                if ($char === ',' && $depth === 0) {
                    $args[] = $this->parseArgument(trim($current), $context);
                    $current = '';
                    continue;
                }
            }
            
            $current .= $char;
        }
        
        if ($current !== '') {
            $args[] = $this->parseArgument(trim($current), $context);
        }
        
        return $args;
    }
    
    /**
     * Parse a single argument
     * 
     * @param string $arg Argument string
     * @param array $context Context data
     * @return mixed Parsed argument value
     */
    protected function parseArgument(string $arg, array $context): mixed
    {
        // String literal
        if ((Str::startsWith($arg, "'") && Str::endsWith($arg, "'")) ||
            (Str::startsWith($arg, '"') && Str::endsWith($arg, '"'))) {
            return trim($arg, "\"'");
        }
        
        // Array literal: {key: value, ...}
        if (Str::startsWith($arg, '{') && Str::endsWith($arg, '}')) {
            return $this->parseArrayLiteral($arg, $context);
        }
        
        // Numeric
        if (is_numeric($arg)) {
            return str_contains($arg, '.') ? (float) $arg : (int) $arg;
        }
        
        // Boolean
        if ($arg === 'true') return true;
        if ($arg === 'false') return false;
        if ($arg === 'null') return null;
        
        // Variable or expression
        return $this->evaluateExpression($arg, $context);
    }
    
    /**
     * Parse array literal from string
     * 
     * @param string $literal Array literal string
     * @param array $context Context data
     * @return array Parsed array
     */
    protected function parseArrayLiteral(string $literal, array $context): array
    {
        $content = trim($literal, '{}');
        if ($content === '') {
            return [];
        }
        
        $result = [];
        $pairs = explode(',', $content);
        
        foreach ($pairs as $pair) {
            if (Str::contains($pair, ':')) {
                [$key, $value] = array_map('trim', explode(':', $pair, 2));
                $key = trim($key, "\"'");
                $result[$key] = $this->parseArgument($value, $context);
            }
        }
        
        return $result;
    }
    
    /**
     * Evaluate dot notation
     * 
     * @param string $expression Dot notation expression
     * @param array $context Context data
     * @return string Result
     */
    protected function evaluateDotNotation(string $expression, array $context): string
    {
        $value = data_get($context, $expression);
        
        if (is_null($value)) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }
}
