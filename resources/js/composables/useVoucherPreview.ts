import { ref, watch } from 'vue';
import type { InspectResponse } from '@/types/voucher';

interface UseVoucherPreviewOptions {
    debounceMs?: number;
    minCodeLength?: number;
}

export function useVoucherPreview(options: UseVoucherPreviewOptions = {}) {
    const debounceMs = options.debounceMs ?? 500;
    const minCodeLength = options.minCodeLength ?? 4;

    const code = ref('');
    const loading = ref(false);
    const error = ref<string | null>(null);
    const voucherData = ref<InspectResponse | null>(null);
    const showPreview = ref(false);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let abortController: AbortController | null = null;

    async function fetchVoucher(voucherCode: string) {
        // Cancel previous request
        if (abortController) {
            abortController.abort();
        }

        // Reset state
        error.value = null;
        voucherData.value = null;
        showPreview.value = true;
        loading.value = true;

        // Create new abort controller
        abortController = new AbortController();

        try {
            const response = await fetch(`/api/v1/vouchers/${voucherCode}/inspect`, {
                signal: abortController.signal,
            });

            const data = await response.json();

            if (data.success) {
                voucherData.value = data;
                error.value = null;
            } else {
                error.value = data.message || 'Voucher not found';
                voucherData.value = null;
            }
        } catch (err: any) {
            if (err.name === 'AbortError') {
                // Request was cancelled, ignore
                return;
            }

            error.value = 'Network error. Please try again.';
            voucherData.value = null;
        } finally {
            loading.value = false;
        }
    }

    function debouncedFetch(newCode: string) {
        // Clear previous timer
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        // Hide preview immediately when code changes
        showPreview.value = false;

        // Trim and uppercase
        const trimmedCode = newCode.trim().toUpperCase();

        // Check minimum length
        if (trimmedCode.length < minCodeLength) {
            return;
        }

        // Start new debounce timer
        debounceTimer = setTimeout(() => {
            fetchVoucher(trimmedCode);
        }, debounceMs);
    }

    // Watch code changes
    watch(code, (newCode) => {
        debouncedFetch(newCode);
    });

    function reset() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        if (abortController) {
            abortController.abort();
        }
        code.value = '';
        loading.value = false;
        error.value = null;
        voucherData.value = null;
        showPreview.value = false;
    }

    function hidePreview() {
        showPreview.value = false;
    }

    return {
        code,
        loading,
        error,
        voucherData,
        showPreview,
        reset,
        hidePreview,
    };
}
