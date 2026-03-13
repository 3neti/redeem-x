<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RedeemWidget from '@/components/RedeemWidget.vue';
import { initializeTheme } from '@/composables/useTheme';

interface Props {
    initial_code?: string | null;
}

defineProps<Props>();

initializeTheme();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-b from-primary/5 via-background to-background p-6 md:p-10">
        <Head title="Disburse Voucher" />
        <div class="w-full max-w-sm">
            <RedeemWidget :initial-code="initial_code" route-prefix="disburse" />
        </div>
    </div>
</template>
