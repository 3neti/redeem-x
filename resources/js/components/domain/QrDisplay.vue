<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    qrCode: string | null;
}

const props = defineProps<Props>();

const qrCodeSrc = computed(() => {
    if (!props.qrCode) return '';
    // If it's already a data URL, use it directly
    if (props.qrCode.startsWith('data:')) return props.qrCode;
    // Otherwise, assume it's base64 and add the data URL prefix
    return `data:image/png;base64,${props.qrCode}`;
});
</script>

<template>
    <div class="flex items-center justify-center">
        <img
            v-if="qrCodeSrc"
            :src="qrCodeSrc"
            alt="QR Code"
            class="max-w-full h-auto"
        />
        <div
            v-else
            class="flex items-center justify-center text-muted-foreground"
        >
            No QR code available
        </div>
    </div>
</template>
