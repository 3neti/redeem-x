<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Standardized API response helper.
 *
 * Provides consistent JSON response format across all API endpoints:
 * - Success: { data: {...}, meta: {...} }
 * - Error: { message: "...", errors: {...}, meta: {...} }
 */
class ApiResponse
{
    /**
     * Return a success response.
     *
     * @param  mixed  $data  Response data (can be array, JsonResource, or collection)
     * @param  int  $status  HTTP status code
     * @param  array  $meta  Additional metadata
     */
    public static function success(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        $response = [];

        // Handle data
        if ($data !== null) {
            if ($data instanceof JsonResource) {
                // Let JsonResource handle its own transformation
                return $data->additional(self::buildMeta($meta))
                    ->response()
                    ->setStatusCode($status);
            }

            $response['data'] = $data;
        }

        // Add metadata
        $response['meta'] = self::buildMeta($meta);

        return response()->json($response, $status);
    }

    /**
     * Return an error response.
     *
     * @param  string  $message  Error message
     * @param  int  $status  HTTP status code
     * @param  array  $errors  Validation errors or detailed error information
     * @param  array  $meta  Additional metadata
     */
    public static function error(
        string $message,
        int $status = 400,
        array $errors = [],
        array $meta = []
    ): JsonResponse {
        $response = [
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        $response['meta'] = self::buildMeta($meta);

        return response()->json($response, $status);
    }

    /**
     * Return a created response (201).
     *
     * @param  mixed  $data  Created resource data
     * @param  array  $meta  Additional metadata
     */
    public static function created(mixed $data, array $meta = []): JsonResponse
    {
        return self::success($data, 201, $meta);
    }

    /**
     * Return a no content response (204).
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return an unauthorized response (401).
     */
    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * Return a forbidden response (403).
     */
    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * Return a not found response (404).
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * Return a validation error response (422).
     *
     * @param  array  $errors  Validation errors
     */
    public static function validationError(
        array $errors,
        string $message = 'The given data was invalid.'
    ): JsonResponse {
        return self::error($message, 422, $errors);
    }

    /**
     * Build metadata array.
     *
     * @param  array  $additional  Additional metadata
     */
    protected static function buildMeta(array $additional = []): array
    {
        $meta = [
            'timestamp' => now()->toIso8601String(),
            'version' => 'v1',
        ];

        return array_merge($meta, $additional);
    }
}
