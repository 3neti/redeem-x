<script setup lang="ts">
import { ref, onErrorCaptured, type Component } from 'vue';
import { AlertCircle, RefreshCw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const props = withDefaults(
    defineProps<{
        fallback?: Component;
        onError?: (error: Error, errorInfo: string) => void;
    }>(),
    {
        fallback: undefined,
        onError: undefined,
    }
);

const hasError = ref(false);
const error = ref<Error | null>(null);
const isDev = import.meta.env.DEV;

onErrorCaptured((err: Error, instance, info) => {
    hasError.value = true;
    error.value = err;

    // Call custom error handler if provided
    if (props.onError) {
        props.onError(err, info);
    }

    // Log to console in development
    if (import.meta.env.DEV) {
        console.error('Error caught by boundary:', err);
        console.error('Component:', instance);
        console.error('Info:', info);
    }

    // Prevent error from propagating
    return false;
});

const reset = () => {
    hasError.value = false;
    error.value = null;
};
</script>

<template>
    <div v-if="hasError">
        <!-- Custom fallback component if provided -->
        <component :is="fallback" v-if="fallback" :error="error" :reset="reset" />

        <!-- Default error UI -->
        <div v-else class="flex items-center justify-center p-8">
            <Alert variant="destructive" class="max-w-2xl">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Something went wrong</AlertTitle>
                <AlertDescription class="mt-2">
                    <p class="mb-4">
                        An unexpected error occurred while loading this content. Please try again.
                    </p>
                    <p v-if="isDev && error" class="mb-4 text-sm font-mono">
                        {{ error.message }}
                    </p>
                    <Button variant="outline" size="sm" @click="reset">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Try Again
                    </Button>
                </AlertDescription>
            </Alert>
        </div>
    </div>

    <!-- Render children when no error -->
    <slot v-else />
</template>
