<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Services;

use LBHurtado\FormFlowManager\Data\DriverConfigData;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * Mapping Engine
 * 
 * Orchestrates the transformation of domain-specific data structures
 * into generic form flow instructions using driver configurations.
 * 
 * Uses TemplateRenderer for variable interpolation and
 * ExpressionEvaluator for conditional logic.
 */
class MappingEngine
{
    public function __construct(
        protected TemplateRenderer $renderer,
        protected ExpressionEvaluator $evaluator
    ) {}
    
    /**
     * Transform source object using driver configuration
     * 
     * @param object $source Source object to transform
     * @param DriverConfigData $driver Driver configuration
     * @return Data Target DTO instance
     * @throws \Exception If transformation fails
     */
    public function transform(object $source, DriverConfigData $driver): Data
    {
        // Build context with helper functions
        $context = $this->buildContext($source, $driver);
        
        // Transform each mapped field
        $result = [];
        foreach ($driver->mappings as $field => $mapping) {
            $result[$field] = $this->mapField($mapping, $context, $driver);
        }
        
        // Apply filters if defined
        if ($driver->hasFilters()) {
            $result = $this->applyFilters($result, $driver->filters, $context);
        }
        
        // Instantiate target class
        $targetClass = $driver->target;
        
        if (!class_exists($targetClass)) {
            throw new \Exception("Target class {$targetClass} does not exist");
        }
        
        return $targetClass::from($result);
    }
    
    /**
     * Build transformation context
     * 
     * @param object $source Source object
     * @param DriverConfigData $driver Driver configuration
     * @return array Context array
     */
    protected function buildContext(object $source, DriverConfigData $driver): array
    {
        return [
            'source' => $source,
            'config' => fn(...$args) => config(...$args),
            'env' => fn($key, $default = null) => env($key, $default),
            'session' => fn($key, $default = null) => session($key, $default),
            'route' => fn(...$args) => route(...$args),
            'url' => fn(...$args) => url(...$args),
            'asset' => fn(...$args) => asset(...$args),
            'now' => fn() => now(),
            'constants' => $driver->constants ?? [],
        ];
    }
    
    /**
     * Map a single field
     * 
     * @param mixed $mapping Mapping configuration
     * @param array $context Transformation context
     * @param DriverConfigData $driver Driver configuration
     * @return mixed Mapped value
     */
    protected function mapField(mixed $mapping, array $context, DriverConfigData $driver): mixed
    {
        // Handle null mapping
        if (is_null($mapping)) {
            return null;
        }
        
        // Handle scalar values (direct assignment)
        if (is_scalar($mapping) && !Str::contains((string) $mapping, '{{')) {
            return $mapping;
        }
        
        // Handle template strings: "voucher_{{ source.code }}"
        if (is_string($mapping) && Str::contains($mapping, '{{')) {
            return $this->renderer->render($mapping, $context);
        }
        
        // Handle array configurations
        if (is_array($mapping)) {
            // Check for special mapping types
            if (isset($mapping['template'])) {
                return $this->handleTemplateMapping($mapping, $context);
            }
            
            if (isset($mapping['source']) && isset($mapping['transform'])) {
                return $this->handleTransformMapping($mapping, $context, $driver);
            }
            
            if (isset($mapping['when'])) {
                return $this->handleConditionalMapping($mapping, $context, $driver);
            }
            
            if (isset($mapping['from'])) {
                return $this->handleFromMapping($mapping, $context, $driver);
            }
            
            // Nested array - recursively map each element
            return $this->mapArray($mapping, $context, $driver);
        }
        
        return $mapping;
    }
    
    /**
     * Handle template mapping: { template: "..." }
     * 
     * @param array $mapping Mapping configuration
     * @param array $context Context
     * @return string Rendered template
     */
    protected function handleTemplateMapping(array $mapping, array $context): string
    {
        return $this->renderer->render($mapping['template'], $context);
    }
    
    /**
     * Handle transform mapping: { source: "...", transform: "array_map", handler: {...} }
     * 
     * @param array $mapping Mapping configuration
     * @param array $context Context
     * @param DriverConfigData $driver Driver configuration
     * @return mixed Transformed value
     */
    protected function handleTransformMapping(array $mapping, array $context, DriverConfigData $driver): mixed
    {
        // Get source data
        $sourceData = data_get($context['source'], $mapping['source']);
        
        if (is_null($sourceData)) {
            return null;
        }
        
        // Apply transformation
        $transform = $mapping['transform'];
        
        return match($transform) {
            'array_map' => $this->transformArrayMap($sourceData, $mapping, $context, $driver),
            'filter' => $this->transformFilter($sourceData, $mapping, $context),
            'first' => $this->transformFirst($sourceData),
            'count' => $this->transformCount($sourceData),
            'join' => $this->transformJoin($sourceData, $mapping),
            default => $sourceData,
        };
    }
    
    /**
     * Transform array_map: map each item through handler
     * 
     * @param mixed $data Source data
     * @param array $mapping Mapping configuration
     * @param array $context Context
     * @param DriverConfigData $driver Driver configuration
     * @return array Mapped array
     */
    protected function transformArrayMap(mixed $data, array $mapping, array $context, DriverConfigData $driver): array
    {
        if (!is_array($data) && !is_iterable($data)) {
            return [];
        }
        
        $result = [];
        $handler = $mapping['handler'] ?? null;
        
        if (!$handler) {
            return collect($data)->values()->all();
        }
        
        foreach ($data as $item) {
            // Add item to context
            $itemContext = array_merge($context, [
                'item' => $item,
                'priorities' => $driver->getConstant('priorities', []),
            ]);
            
            // Map the handler configuration
            $mappedItem = $this->mapField($handler, $itemContext, $driver);
            $result[] = $mappedItem;
        }
        
        return $result;
    }
    
    /**
     * Transform filter: filter array by condition
     * 
     * @param mixed $data Source data
     * @param array $mapping Mapping configuration
     * @param array $context Context
     * @return array Filtered array
     */
    protected function transformFilter(mixed $data, array $mapping, array $context): array
    {
        if (!is_array($data) && !is_iterable($data)) {
            return [];
        }
        
        $condition = $mapping['condition'] ?? null;
        if (!$condition) {
            return collect($data)->values()->all();
        }
        
        $result = [];
        foreach ($data as $item) {
            $itemContext = array_merge($context, ['item' => $item]);
            
            if ($this->evaluator->evaluate($condition, $itemContext)) {
                $result[] = $item;
            }
        }
        
        return $result;
    }
    
    /**
     * Transform first: get first element
     * 
     * @param mixed $data Source data
     * @return mixed First element or null
     */
    protected function transformFirst(mixed $data): mixed
    {
        if (is_array($data)) {
            return reset($data) ?: null;
        }
        
        return $data;
    }
    
    /**
     * Transform count: count elements
     * 
     * @param mixed $data Source data
     * @return int Count
     */
    protected function transformCount(mixed $data): int
    {
        if (is_array($data) || is_countable($data)) {
            return count($data);
        }
        
        return 0;
    }
    
    /**
     * Transform join: join array elements
     * 
     * @param mixed $data Source data
     * @param array $mapping Mapping configuration
     * @return string Joined string
     */
    protected function transformJoin(mixed $data, array $mapping): string
    {
        if (!is_array($data)) {
            return (string) $data;
        }
        
        $separator = $mapping['separator'] ?? ', ';
        return implode($separator, $data);
    }
    
    /**
     * Handle conditional mapping: { when: "...", then: {...}, else: {...} }
     * 
     * @param array $mapping Mapping configuration
     * @param array $context Context
     * @param DriverConfigData $driver Driver configuration
     * @return mixed Mapped value
     */
    protected function handleConditionalMapping(array $mapping, array $context, DriverConfigData $driver): mixed
    {
        $condition = $mapping['when'];
        
        if ($this->evaluator->evaluate($condition, $context)) {
            return isset($mapping['then']) 
                ? $this->mapField($mapping['then'], $context, $driver)
                : true;
        }
        
        return isset($mapping['else'])
            ? $this->mapField($mapping['else'], $context, $driver)
            : null;
    }
    
    /**
     * Handle from mapping: { from: "...", when: "..." }
     * 
     * @param array $mapping Mapping configuration
     * @param array $context Context
     * @param DriverConfigData $driver Driver configuration
     * @return mixed Mapped value
     */
    protected function handleFromMapping(array $mapping, array $context, DriverConfigData $driver): mixed
    {
        // Check condition if present
        if (isset($mapping['when'])) {
            $condition = $mapping['when'];
            if (!$this->evaluator->evaluate($condition, $context)) {
                return null;
            }
        }
        
        // Get value from 'from' field
        $from = $mapping['from'];
        
        // Handle nested object/array
        if (is_array($from)) {
            return $this->mapField($from, $context, $driver);
        }
        
        // Handle template string
        if (Str::contains($from, '{{')) {
            return $this->renderer->render($from, $context);
        }
        
        // Handle dot notation
        if (Str::contains($from, '.')) {
            return data_get($context, $from);
        }
        
        return $from;
    }
    
    /**
     * Map array recursively
     * 
     * @param array $array Array to map
     * @param array $context Context
     * @param DriverConfigData $driver Driver configuration
     * @return array Mapped array
     */
    protected function mapArray(array $array, array $context, DriverConfigData $driver): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $result[$key] = $this->mapField($value, $context, $driver);
        }
        
        return $result;
    }
    
    /**
     * Apply filters to result
     * 
     * @param array $result Transformation result
     * @param array $filters Filter configuration
     * @param array $context Context
     * @return array Filtered result
     */
    protected function applyFilters(array $result, array $filters, array $context): array
    {
        // Check skip_flow_if conditions
        if (isset($filters['skip_flow_if'])) {
            foreach ($filters['skip_flow_if'] as $condition) {
                if ($this->evaluator->evaluate($condition, $context)) {
                    // Return empty or minimal result to skip flow
                    return [];
                }
            }
        }
        
        // Filter steps if defined
        if (isset($filters['steps']) && isset($result['steps'])) {
            $stepFilters = $filters['steps'];
            $filteredSteps = [];
            
            foreach ($result['steps'] as $step) {
                $stepContext = array_merge($context, ['item' => $step]);
                $include = true;
                
                // Check all filter conditions
                foreach ($stepFilters as $condition) {
                    if (!$this->evaluator->evaluate($condition, $stepContext)) {
                        $include = false;
                        break;
                    }
                }
                
                if ($include) {
                    $filteredSteps[] = $step;
                }
            }
            
            $result['steps'] = $filteredSteps;
        }
        
        return $result;
    }
}
