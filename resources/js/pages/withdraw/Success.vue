<script setup lang="ts">
import { router, Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { CheckCircle2, ArrowRight } from 'lucide-vue-next';
import { initializeTheme } from '@/composables/useTheme';

initializeTheme();

interface Props {
    voucher: {
        code: string;
        currency: string;
        slice_mode: 'fixed' | 'open';
    };
    result: {
        amount: number;
        formatted_amount: string;
        slice_number: number;
        remaining_slices: number;
        remaining_balance: number;
        formatted_remaining: string;
        can_withdraw_more: boolean;
    };
}

defineProps<Props>();
</script>

<template>
    <Head title="Withdrawal Successful" />

    <div class="min-h-screen bg-gradient-to-b from-primary/5 via-background to-background px-5 py-8">
        <div class="mx-auto max-w-md space-y-8">

            <!-- Hero -->
            <div class="text-center pt-4 space-y-4">
                <CheckCircle2 class="h-8 w-8 text-green-500 mx-auto" />

                <p class="text-lg font-medium text-foreground">
                    Withdrawal Successful
                </p>

                <!-- Amount -->
                <p class="text-2xl font-bold tracking-tight text-foreground">
                    {{ result.formatted_amount }}
                </p>

                <!-- Voucher code badge -->
                <div class="inline-flex items-center gap-1.5 px-4 py-1 text-sm font-mono font-semibold tracking-widest text-primary bg-primary/5 border border-primary/20 rounded-full">
                    <span class="text-primary/40" aria-hidden="true">||</span>
                    {{ voucher.code }}
                    <span class="text-primary/40" aria-hidden="true">||</span>
                </div>

                <p class="text-sm text-muted-foreground">
                    Slice {{ result.slice_number }} disbursed to your account
                </p>
            </div>

            <!-- Remaining summary -->
            <div class="rounded-xl border bg-card p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Remaining Balance</span>
                    <span class="font-semibold text-primary">{{ result.formatted_remaining }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Slices Remaining</span>
                    <span class="font-medium">{{ result.remaining_slices }}</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <Button
                    v-if="result.can_withdraw_more"
                    class="w-full rounded-full"
                    @click="router.visit(`/withdraw?code=${voucher.code}`)"
                >
                    Withdraw Another Slice
                    <ArrowRight class="w-4 h-4 ml-1" />
                </Button>
                <p v-else class="text-center text-sm text-muted-foreground">
                    This voucher has been fully consumed.
                </p>
                <Button
                    variant="ghost"
                    size="lg"
                    class="w-full rounded-full"
                    @click="router.visit('/disburse')"
                >
                    Redeem Another Voucher
                </Button>
            </div>
        </div>
    </div>
</template>
