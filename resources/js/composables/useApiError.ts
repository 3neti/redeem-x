import { AxiosError } from 'axios';
import { router } from '@inertiajs/vue3';

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
    statusCode?: number;
}

export function useApiError() {
    /**
     * Handle API errors and convert to user-friendly format
     */
    const handleError = (error: unknown): ApiError => {
        // Default error
        const defaultError: ApiError = {
            message: 'An unexpected error occurred. Please try again.',
        };

        // Not an error object
        if (!(error instanceof Error)) {
            return defaultError;
        }

        // Axios error with response
        if (error instanceof AxiosError && error.response) {
            const { status, data } = error.response;

            // Validation errors (422)
            if (status === 422 && data.errors) {
                return {
                    message: data.message || 'Validation failed',
                    errors: data.errors,
                    statusCode: status,
                };
            }

            // Authentication error (401)
            if (status === 401) {
                // Redirect to login
                router.visit('/login');
                return {
                    message: 'You need to log in to continue.',
                    statusCode: status,
                };
            }

            // Authorization error (403)
            if (status === 403) {
                return {
                    message: data.message || 'You do not have permission to perform this action.',
                    statusCode: status,
                };
            }

            // Not found (404)
            if (status === 404) {
                return {
                    message: data.message || 'The requested resource was not found.',
                    statusCode: status,
                };
            }

            // Rate limited (429)
            if (status === 429) {
                return {
                    message: 'Too many requests. Please try again later.',
                    statusCode: status,
                };
            }

            // Server errors (5xx)
            if (status >= 500) {
                return {
                    message: 'A server error occurred. Please try again later.',
                    statusCode: status,
                };
            }

            // Other API errors
            return {
                message: data.message || defaultError.message,
                statusCode: status,
            };
        }

        // Network error (no response)
        if (error instanceof AxiosError && !error.response) {
            return {
                message: 'Network error. Please check your connection and try again.',
            };
        }

        // Generic error
        return {
            message: error.message || defaultError.message,
        };
    };

    /**
     * Get validation error for a specific field
     */
    const getFieldError = (errors: Record<string, string[]> | undefined, field: string): string | undefined => {
        if (!errors || !errors[field]) {
            return undefined;
        }
        return errors[field][0];
    };

    /**
     * Check if error is a validation error
     */
    const isValidationError = (error: ApiError): boolean => {
        return error.statusCode === 422 && !!error.errors;
    };

    /**
     * Check if error is retryable
     */
    const isRetryable = (error: ApiError): boolean => {
        if (!error.statusCode) {
            return true; // Network errors are retryable
        }
        // Retry on server errors and rate limits
        return error.statusCode >= 500 || error.statusCode === 429;
    };

    return {
        handleError,
        getFieldError,
        isValidationError,
        isRetryable,
    };
}
