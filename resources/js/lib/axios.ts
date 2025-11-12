import axios, { AxiosError, AxiosRequestConfig } from 'axios';

// Debug flag - set to false to suppress console logs
const DEBUG = false;

// Augment AxiosRequestConfig to include metadata
declare module 'axios' {
    export interface AxiosRequestConfig {
        metadata?: {
            startTime: number;
        };
    }
}

// Configure axios for Laravel Sanctum SPA authentication
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.timeout = 30000; // 30 second timeout

// Retry configuration
const MAX_RETRIES = 3;
const RETRY_DELAY = 1000; // Start with 1 second

// Track retry counts per request
interface RetryConfig extends AxiosRequestConfig {
    __retryCount?: number;
}

// Exponential backoff delay
const getRetryDelay = (retryCount: number): number => {
    return RETRY_DELAY * Math.pow(2, retryCount);
};

// Determine if error is retryable
const isRetryableError = (error: AxiosError): boolean => {
    if (!error.response) {
        // Network errors (no response) are retryable
        return true;
    }

    // Retry on 5xx errors (server errors) and 429 (rate limit)
    const status = error.response.status;
    return status >= 500 || status === 429;
};

// Request interceptor for debugging
axios.interceptors.request.use(
    (config) => {
        // Add request timestamp for monitoring
        config.metadata = { startTime: new Date().getTime() };
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor for retry logic
axios.interceptors.response.use(
    (response) => {
        // Log response time in dev mode
        if (DEBUG && import.meta.env.DEV && response.config.metadata) {
            const duration = new Date().getTime() - response.config.metadata.startTime;
            console.log(`[Axios] ${response.config.method?.toUpperCase()} ${response.config.url} - ${duration}ms`);
        }
        return response;
    },
    async (error: AxiosError) => {
        const config = error.config as RetryConfig;

        // If no config or retry not applicable, reject immediately
        if (!config) {
            return Promise.reject(error);
        }

        // Initialize retry count
        config.__retryCount = config.__retryCount || 0;

        // Check if we should retry
        const shouldRetry = config.__retryCount < MAX_RETRIES && isRetryableError(error);

        if (!shouldRetry) {
            // Log error in dev mode
            if (DEBUG && import.meta.env.DEV) {
                console.error('[Axios] Request failed:', {
                    url: config.url,
                    method: config.method,
                    status: error.response?.status,
                    message: error.message,
                });
            }
            return Promise.reject(error);
        }

        // Increment retry count
        config.__retryCount++;

        // Calculate delay with exponential backoff
        const delay = getRetryDelay(config.__retryCount - 1);

        // Log retry attempt in dev mode
        if (DEBUG && import.meta.env.DEV) {
            console.warn(`[Axios] Retrying request (${config.__retryCount}/${MAX_RETRIES}) after ${delay}ms...`);
        }

        // Wait before retrying
        await new Promise(resolve => setTimeout(resolve, delay));

        // Retry the request
        return axios(config);
    }
);

export default axios;
