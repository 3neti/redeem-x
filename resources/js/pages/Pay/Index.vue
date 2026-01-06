<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PayWidget from '@/components/PayWidget.vue';

interface Props {
    initial_code?: string | null;
}

defineProps<Props>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Pay Voucher" />
        <div class="w-full max-w-sm">
            <PayWidget :initial-code="initial_code" />
        </div>
    </div>
</template>
