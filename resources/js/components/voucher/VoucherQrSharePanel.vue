<script setup lang="ts">
import { computed } from 'vue';
import QrSharePanel from '@/components/QrSharePanel.vue';
import type { VoucherQrData } from '@/composables/useVoucherQr';

interface Props {
    qrData: VoucherQrData | null;
    amount?: number;
    currency?: string;
}

const props = defineProps<Props>();

// Transform voucher QR data to QrSharePanel format
const shareData = computed(() => {
    if (!props.qrData) return null;
    
    return {
        qr_code: props.qrData.qr_code,
        qr_url: props.qrData.redemption_url,
        shareable_url: props.qrData.redemption_url,
        amount: props.amount, // Optional: for share message
    };
});
</script>

<template>
    <QrSharePanel :qr-data="shareData" />
</template>
