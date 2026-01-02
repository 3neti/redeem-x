<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Ticket, CheckCircle2, Loader2 } from 'lucide-vue-next';

const voucherCode = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const success = ref(false);
const successData = ref<{ amount: number; newBalance: number } | null>(null);

const resetForm = () => {
    voucherCode.value = '';
    error.value = null;
    success.value = false;
    successData.value = null;
};

const formatAmount = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const handleSubmit = async () => {
    if (!voucherCode.value.trim()) {
        error.value = 'Please enter a voucher code';
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.post('/pay/voucher', {
            code: voucherCode.value.trim().toUpperCase(),
        });

        if (data.success) {
            success.value = true;
            successData.value = {
                amount: data.amount,
                newBalance: data.new_balance,
            };
            
            // Auto-reset form after 3 seconds and reload to show updated balance
            setTimeout(() => {
                resetForm();
                router.reload();
            }, 3000);
        } else {
            error.value = data.error || 'Failed to redeem voucher';
        }
    } catch (e: any) {
        const errorMessage = e.response?.data?.errors?.code?.[0] || 
                           e.response?.data?.message || 
                           e.message || 
                           'An error occurred';
        error.value = errorMessage;
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Ticket class="h-5 w-5" />
                Pay with Voucher
            </CardTitle>
            <CardDescription>
                Redeem a voucher code to add funds instantly
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div v-if="!success" class="space-y-4">
                <!-- Voucher Code Input -->
                <div class="space-y-2">
                    <Label for="voucherCode">Voucher Code</Label>
                    <Input
                        id="voucherCode"
                        v-model="voucherCode"
                        type="text"
                        placeholder="Enter voucher code"
                        class="font-mono uppercase"
                        :disabled="loading"
                        @keyup.enter="handleSubmit"
                        @input="voucherCode = voucherCode.toUpperCase()"
                    />
                    <p class="text-xs text-muted-foreground">
                        Case-insensitive. The voucher value will be credited immediately.
                    </p>
                </div>

                <!-- Error Alert -->
                <Alert v-if="error" variant="destructive">
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>

                <!-- Info Alert -->
                <Alert v-else>
                    <AlertDescription class="text-sm">
                        <strong>Note:</strong> Vouchers can only be redeemed once. Funds are added to your wallet instantly.
                    </AlertDescription>
                </Alert>

                <!-- Submit Button -->
                <Button
                    class="w-full"
                    @click="handleSubmit"
                    :disabled="loading || !voucherCode.trim()"
                >
                    <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                    {{ loading ? 'Processing...' : 'Redeem Voucher' }}
                </Button>
            </div>

            <!-- Success State -->
            <div v-else class="space-y-4">
                <div class="flex flex-col items-center justify-center py-4 text-center">
                    <div class="rounded-full bg-green-100 p-3 mb-4">
                        <CheckCircle2 class="h-8 w-8 text-green-600" />
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Voucher Redeemed!</h3>
                    <p class="text-muted-foreground mb-4">
                        {{ formatAmount(successData?.amount || 0) }} has been added to your wallet
                    </p>
                    <div class="w-full rounded-lg bg-muted p-4">
                        <p class="text-sm text-muted-foreground mb-1">New Balance</p>
                        <p class="text-2xl font-bold">
                            {{ formatAmount(successData?.newBalance || 0) }}
                        </p>
                    </div>
                </div>
                <p class="text-xs text-center text-muted-foreground">
                    Page will refresh automatically...
                </p>
            </div>
        </CardContent>
    </Card>
</template>
