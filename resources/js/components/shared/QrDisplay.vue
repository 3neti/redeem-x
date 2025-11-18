<script setup lang="ts">
import { computed } from 'vue';
import { Loader2, AlertCircle, QrCode } from 'lucide-vue-next';

interface Props {
    qrCode: string | null;
    loading?: boolean;
    error?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    error: null,
});

const qrCodeSrc = computed(() => {
    if (!props.qrCode) return '';
    // If it's already a data URL, use it directly
    if (props.qrCode.startsWith('data:')) return props.qrCode;
    // Otherwise, assume it's base64 and add the data URL prefix
    return `data:image/png;base64,${props.qrCode}`;
});
</script>

<template>
    <div
        class="flex items-center justify-center rounded-lg border bg-card p-4"
        :class="{
            'min-h-[288px]': !qrCode,
        }"
    >
        <!-- Loading State -->
        <div
            v-if="loading"
            class="flex flex-col items-center justify-center gap-3 text-muted-foreground"
        >
            <Loader2 class="h-12 w-12 animate-spin" />
            <p class="text-sm">Generating QR code...</p>
        </div>

        <!-- Error State -->
        <div
            v-else-if="error"
            class="flex flex-col items-center justify-center gap-3 text-destructive"
        >
            <AlertCircle class="h-12 w-12" />
            <p class="text-sm text-center max-w-[250px]">{{ error }}</p>
        </div>

        <!-- No QR Code (Empty State) -->
        <div
            v-else-if="!qrCodeSrc"
            class="flex flex-col items-center justify-center gap-3 text-muted-foreground"
        >
            <QrCode class="h-12 w-12" />
            <p class="text-sm">No QR code available</p>
        </div>

        <!-- QR Code Image -->
        <img
            v-else
            :src="qrCodeSrc"
            alt="QR Code"
            class="max-w-full h-auto rounded-md"
        />
    </div>
</template>
