import { ref, onMounted } from 'vue';
import axios from '@/lib/axios';

export function useQrCode(account: string, amount: number) {
    const qrCode = ref<string | null>(null);
    const status = ref<'idle' | 'loading' | 'success' | 'error'>(
        amount > 0 ? 'idle' : 'error'
    );
    const message = ref('');

    const fetchQr = async () => {
        status.value = 'loading';
        message.value = 'Generating QR code…';

        try {
            const { data } = await axios.get(route('wallet.add-funds'), {
                params: { account, amount },
            });

            if (data.event.name === 'qrcode.generated') {
                qrCode.value = data.event.data;
                status.value = 'success';
                message.value = 'QR code generated.';
            } else {
                status.value = 'error';
                message.value = data.message || 'Failed to generate QR code.';
            }
        } catch (error) {
            status.value = 'error';
            message.value = 'Error occurred while generating QR code.';
            console.error('[useQrCode] Error:', error);
        }
    };

    onMounted(fetchQr);

    return {
        qrCode,
        status,
        message,
        refresh: fetchQr,
    };
}
