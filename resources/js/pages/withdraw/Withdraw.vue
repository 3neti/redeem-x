<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Wallet, ArrowRight, AlertCircle } from 'lucide-vue-next';
import { initializeTheme } from '@/composables/useTheme';

initializeTheme();

interface VoucherProps {
    code: string;
    amount: number;
    currency: string;
    formatted_amount: string;
    slice_mode: 'fixed' | 'open';
    slice_amount: number | null;
    formatted_slice_amount: string | null;
    max_slices: number;
    min_withdrawal: number;
    consumed_slices: number;
    remaining_slices: number;
    remaining_balance: number;
    formatted_remaining: string;
}

interface Props {
    voucher: VoucherProps;
}

const props = defineProps<Props>();
const page = usePage();

const mobile = ref('');
const amount = ref<number | null>(null);
const isSubmitting = ref(false);

const errors = computed(() => page.props.errors as Record<string, string>);

const isFixed = computed(() => props.voucher.slice_mode === 'fixed');

const progressPercent = computed(() => {
    if (props.voucher.max_slices === 0) return 0;
    return Math.round((props.voucher.consumed_slices / props.voucher.max_slices) * 100);
});

const handleSubmit = () => {
    isSubmitting.value = true;

    const data: Record<string, any> = { mobile: mobile.value };
    if (!isFixed.value && amount.value) {
        data.amount = amount.value;
    }

    router.post(`/withdraw/${props.voucher.code}`, data, {
        onFinish: () => { isSubmitting.value = false; },
    });
};
</script>

<template>
    <Head title="Withdraw Funds" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-b from-primary/5 via-background to-background p-6 md:p-10">
        <div class="w-full max-w-sm space-y-6">

            <!-- Header -->
            <div class="text-center space-y-2">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary/10 mb-2">
                    <Wallet class="w-6 h-6 text-primary" />
                </div>
                <h1 class="text-xl font-semibold text-foreground">Withdraw Funds</h1>
                <div class="inline-flex items-center gap-1.5 px-4 py-1 text-sm font-mono font-semibold tracking-widest text-primary bg-primary/5 border border-primary/20 rounded-full">
                    {{ voucher.code }}
                </div>
            </div>

            <!-- Voucher Summary Card -->
            <div class="rounded-xl border bg-card p-4 space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Face Value</span>
                    <span class="font-medium">{{ voucher.formatted_amount }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Remaining</span>
                    <span class="font-semibold text-primary">{{ voucher.formatted_remaining }}</span>
                </div>

                <!-- Progress bar -->
                <div class="space-y-1">
                    <div class="flex justify-between text-xs text-muted-foreground">
                        <span>{{ voucher.consumed_slices }} of {{ voucher.max_slices }} slices used</span>
                        <span>{{ voucher.remaining_slices }} left</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                        <div
                            class="h-full rounded-full bg-primary transition-all duration-500"
                            :style="{ width: progressPercent + '%' }"
                        />
                    </div>
                </div>

                <!-- Next withdrawal info -->
                <div v-if="isFixed" class="flex justify-between text-sm pt-1 border-t">
                    <span class="text-muted-foreground">Next withdrawal</span>
                    <span class="font-semibold">{{ voucher.formatted_slice_amount }}</span>
                </div>
                <div v-else class="text-xs text-muted-foreground pt-1 border-t">
                    Min: {{ voucher.currency }} {{ voucher.min_withdrawal.toLocaleString() }} per withdrawal
                </div>
            </div>

            <!-- Error banner -->
            <div v-if="errors.withdrawal" class="flex items-start gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
                <AlertCircle class="w-4 h-4 mt-0.5 shrink-0" />
                <span>{{ errors.withdrawal }}</span>
            </div>

            <!-- Withdrawal Form -->
            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Mobile verification -->
                <div class="space-y-2">
                    <Label for="mobile">Mobile Number</Label>
                    <Input
                        id="mobile"
                        v-model="mobile"
                        type="tel"
                        placeholder="09XX XXX XXXX"
                        required
                        :class="{ 'border-destructive': errors.mobile }"
                    />
                    <p class="text-xs text-muted-foreground">
                        Enter the mobile number used during redemption
                    </p>
                    <p v-if="errors.mobile" class="text-xs text-destructive">{{ errors.mobile }}</p>
                </div>

                <!-- Amount input (open mode only) -->
                <div v-if="!isFixed" class="space-y-2">
                    <Label for="amount">Withdrawal Amount ({{ voucher.currency }})</Label>
                    <Input
                        id="amount"
                        v-model.number="amount"
                        type="number"
                        :min="voucher.min_withdrawal"
                        :max="voucher.remaining_balance"
                        :placeholder="`Min ${voucher.min_withdrawal}`"
                        required
                        :class="{ 'border-destructive': errors.amount }"
                    />
                    <p v-if="errors.amount" class="text-xs text-destructive">{{ errors.amount }}</p>
                </div>

                <Button
                    type="submit"
                    class="w-full rounded-full"
                    :disabled="isSubmitting || !mobile"
                >
                    <template v-if="isSubmitting">Processing...</template>
                    <template v-else>
                        Withdraw {{ isFixed ? voucher.formatted_slice_amount : '' }}
                        <ArrowRight class="w-4 h-4 ml-1" />
                    </template>
                </Button>
            </form>

            <!-- Back link -->
            <div class="text-center">
                <button
                    class="text-sm text-muted-foreground hover:text-foreground underline-offset-4 hover:underline"
                    @click="router.visit('/disburse')"
                >
                    Back to Redeem
                </button>
            </div>
        </div>
    </div>
</template>
