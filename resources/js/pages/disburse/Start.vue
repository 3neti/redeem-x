<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RedeemWidget from '@/components/RedeemWidget.vue';

interface Props {
    initial_code?: string | null;
}

defineProps<Props>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-b from-amber-50/80 via-white to-gray-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 p-6 md:p-10">
        <Head title="Disburse Voucher" />
        <div class="w-full max-w-sm">
            <RedeemWidget :initial-code="initial_code" route-prefix="disburse" />
        </div>
    </div>
</template>
