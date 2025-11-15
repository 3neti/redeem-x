import { ref, Ref } from 'vue';
import axios from '@/lib/axios';

export interface MerchantData {
    name: string;
    city: string | null;
    description: string | null;
    category: string;
}

export interface QrCodeData {
    qr_code: string;
    qr_url: string | null;
    qr_id: string;
    expires_at: string | null;
    account: string;
    amount: number | null;
    shareable_url: string;
    merchant: MerchantData;
}

export interface UseQrGenerationReturn {
    qrData: Ref<QrCodeData | null>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    generate: (amount?: number) => Promise<void>;
    regenerate: () => Promise<void>;
}

/**
 * Composable for generating QR codes via Omnipay gateway
 * 
 * @param autoGenerate - Whether to automatically generate QR on mount (default: true)
 * @param initialAmount - Initial amount for QR generation (0 for dynamic)
 */
export function useQrGeneration(
    autoGenerate: boolean = true,
    initialAmount: number = 0
): UseQrGenerationReturn {
    const qrData = ref<QrCodeData | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const currentAmount = ref(initialAmount);

    const generate = async (amount: number = 0, force: boolean = false) => {
        loading.value = true;
        error.value = null;
        currentAmount.value = amount;

        try {
            const { data } = await axios.post('/api/v1/wallet/generate-qr', {
                amount: amount > 0 ? amount : 0,
                force: force,
            });

            if (data.success) {
                qrData.value = data.data;
            } else {
                throw new Error(data.message || 'Failed to generate QR code');
            }
        } catch (err: any) {
            const message =
                err.response?.data?.message ||
                err.message ||
                'Error occurred while generating QR code';
            error.value = message;
            console.error('[useQrGeneration] Error:', err);
        } finally {
            loading.value = false;
        }
    };

    const regenerate = async () => {
        // Force regenerate bypasses cache
        await generate(currentAmount.value, true);
    };

    // Auto-generate on mount if enabled
    if (autoGenerate) {
        generate(initialAmount);
    }

    return {
        qrData,
        loading,
        error,
        generate,
        regenerate,
    };
}
