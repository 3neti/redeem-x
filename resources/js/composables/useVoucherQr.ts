import { ref, unref, type MaybeRef } from 'vue';
import QRCode from 'qrcode';

export interface VoucherQrData {
    qr_code: string;       // Base64 data URL
    redemption_url: string; // Full redemption URL
    voucher_code: string;   // Just the code
}

export function useVoucherQr(voucherCode: MaybeRef<string>, redemptionPath: MaybeRef<string> = '/disburse') {
    const qrData = ref<VoucherQrData | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const generateQr = async () => {
        loading.value = true;
        error.value = null;

        try {
            // Unwrap reactive values at generation time
            const code = unref(voucherCode);
            const path = unref(redemptionPath);
            const redemptionUrl = `${window.location.origin}${path}?code=${code}`;
            
            // Generate QR code as data URL
            const qrCode = await QRCode.toDataURL(redemptionUrl, {
                width: 300,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF',
                },
            });

            qrData.value = {
                qr_code: qrCode,
                redemption_url: redemptionUrl,
                voucher_code: code,
            };
        } catch (err: any) {
            error.value = err.message || 'Failed to generate QR code';
        } finally {
            loading.value = false;
        }
    };

    return {
        qrData,
        loading,
        error,
        generateQr,
    };
}
