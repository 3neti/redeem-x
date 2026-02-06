<?php

namespace LBHurtado\SettlementEnvelope\Services;

use LBHurtado\SettlementEnvelope\Data\DriverData;
use LBHurtado\SettlementEnvelope\Exceptions\PayloadValidationException;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;

class PayloadValidator
{
    protected Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
        $this->validator->setMaxErrors(10);
    }

    /**
     * Validate payload against driver schema
     */
    public function validate(array $payload, ?DriverData $driver = null, ?array $schema = null): bool
    {
        if (!$schema) {
            // If no schema provided, assume valid
            return true;
        }

        // Convert to object for JSON Schema validation
        $data = json_decode(json_encode($payload));
        $schemaObject = json_decode(json_encode($schema));

        $result = $this->validator->validate($data, $schemaObject);

        if (!$result->isValid()) {
            $formatter = new ErrorFormatter();
            $errors = $formatter->format($result->error());

            throw new PayloadValidationException(
                'Payload validation failed',
                $this->flattenErrors($errors)
            );
        }

        return true;
    }

    /**
     * Check if a specific field exists in payload (using JSON pointer)
     */
    public function fieldExists(array $payload, string $pointer): bool
    {
        $path = $this->parseJsonPointer($pointer);
        $current = $payload;

        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    /**
     * Get value from payload using JSON pointer
     */
    public function getFieldValue(array $payload, string $pointer): mixed
    {
        $path = $this->parseJsonPointer($pointer);
        $current = $payload;

        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Parse JSON pointer to path segments
     * e.g., "/loan/tcp" -> ["loan", "tcp"]
     */
    protected function parseJsonPointer(string $pointer): array
    {
        if ($pointer === '' || $pointer === '/') {
            return [];
        }

        // Remove leading slash and split
        $pointer = ltrim($pointer, '/');
        $parts = explode('/', $pointer);

        // Unescape JSON pointer escapes
        return array_map(function ($part) {
            return str_replace(['~1', '~0'], ['/', '~'], $part);
        }, $parts);
    }

    /**
     * Flatten nested errors into a simple array
     */
    protected function flattenErrors(array $errors, string $prefix = ''): array
    {
        $flat = [];

        foreach ($errors as $key => $value) {
            $path = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenErrors($value, $path));
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    /**
     * Merge patch into existing payload
     */
    public function mergePatch(array $existing, array $patch): array
    {
        return array_replace_recursive($existing, $patch);
    }

    /**
     * Compute diff between two payloads
     */
    public function computeDiff(array $old, array $new): array
    {
        $diff = [];

        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $old)) {
                $diff[$key] = ['added' => $value];
            } elseif (is_array($value) && is_array($old[$key])) {
                $subDiff = $this->computeDiff($old[$key], $value);
                if (!empty($subDiff)) {
                    $diff[$key] = $subDiff;
                }
            } elseif ($old[$key] !== $value) {
                $diff[$key] = ['from' => $old[$key], 'to' => $value];
            }
        }

        foreach ($old as $key => $value) {
            if (!array_key_exists($key, $new)) {
                $diff[$key] = ['removed' => $value];
            }
        }

        return $diff;
    }
}
