import { ref, watch, type Ref } from 'vue';

/**
 * Creates a debounced version of a value
 * 
 * @param value - The reactive value to debounce
 * @param delay - Delay in milliseconds (default: 300ms)
 * @returns A debounced ref
 */
export function useDebounce<T>(value: Ref<T>, delay: number = 300): Ref<T> {
    const debouncedValue = ref(value.value) as Ref<T>;
    let timeout: ReturnType<typeof setTimeout> | null = null;

    watch(value, (newValue) => {
        if (timeout) {
            clearTimeout(timeout);
        }

        timeout = setTimeout(() => {
            debouncedValue.value = newValue;
        }, delay);
    });

    return debouncedValue;
}

/**
 * Creates a debounced function
 * 
 * @param fn - The function to debounce
 * @param delay - Delay in milliseconds (default: 300ms)
 * @returns A debounced function
 */
export function useDebounceFn<T extends (...args: any[]) => any>(
    fn: T,
    delay: number = 300
): (...args: Parameters<T>) => void {
    let timeout: ReturnType<typeof setTimeout> | null = null;

    return function (...args: Parameters<T>) {
        if (timeout) {
            clearTimeout(timeout);
        }

        timeout = setTimeout(() => {
            fn(...args);
        }, delay);
    };
}
