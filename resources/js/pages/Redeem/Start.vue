<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RedeemWidget from '@/components/RedeemWidget.vue';
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Redeem Voucher" />
        <div class="w-full max-w-sm">
            <RedeemWidget />
        </div>
    </div>
</template>
