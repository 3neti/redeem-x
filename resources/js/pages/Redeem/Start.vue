<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useRedemptionApi } from '@/composables/useRedemptionApi';
import type { ValidateVoucherResponse } from '@/composables/useRedemptionApi';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle, XCircle, Loader2 } from 'lucide-vue-next';

const { loading, error, validateVoucher } = useRedemptionApi();

const voucherCode = ref('');
const validatedVoucher = ref<ValidateVoucherResponse | null>(null);
const showValidation = ref(false);

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const handleValidate = async () => {
    if (!voucherCode.value.trim()) {
        return;
    }

    showValidation.value = false;
    validatedVoucher.value = null;

    try {
        const result = await validateVoucher(voucherCode.value.trim().toUpperCase());
        validatedVoucher.value = result;
        showValidation.value = true;
    } catch (err) {
        showValidation.value = true;
    }
};

const handleContinue = () => {
    if (!validatedVoucher.value) return;
    
    router.visit(`/redeem/${validatedVoucher.value.voucher.code}/wallet`);
};

const handleReset = () => {
    voucherCode.value = '';
    validatedVoucher.value = null;
    showValidation.value = false;
};
</script>

<template>
    <PublicLayout>
        <div class="flex min-h-[80vh] items-center justify-center px-4">
            <Card class="w-full max-w-md">
                <CardHeader class="text-center">
                    <CardTitle class="text-2xl">Redeem Voucher</CardTitle>
                    <CardDescription>
                        Enter your voucher code to start the redemption process
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Input Form -->
                    <form v-if="!showValidation" @submit.prevent="handleValidate" class="space-y-4">
                        <div class="space-y-2">
                            <label for="voucher-code" class="text-sm font-medium">
                                Voucher Code
                            </label>
                            <Input
                                id="voucher-code"
                                v-model="voucherCode"
                                type="text"
                                placeholder="Enter voucher code"
                                required
                                :disabled="loading"
                                class="text-center text-lg uppercase tracking-wider"
                                @input="voucherCode = voucherCode.toUpperCase()"
                            />
                        </div>
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="!voucherCode.trim() || loading"
                        >
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            {{ loading ? 'Validating...' : 'Validate Voucher' }}
                        </Button>
                    </form>

                    <!-- Validation Result -->
                    <div v-if="showValidation" class="space-y-4">
                        <!-- Error State -->
                        <Alert v-if="error" variant="destructive">
                            <XCircle class="h-4 w-4" />
                            <AlertDescription>
                                {{ error }}
                            </AlertDescription>
                        </Alert>

                        <!-- Success State -->
                        <div v-else-if="validatedVoucher">
                            <Alert v-if="validatedVoucher.can_redeem" class="border-green-500 bg-green-50">
                                <CheckCircle class="h-4 w-4 text-green-600" />
                                <AlertDescription class="text-green-900">
                                    Voucher is valid and ready to redeem!
                                </AlertDescription>
                            </Alert>
                            <Alert v-else variant="destructive">
                                <XCircle class="h-4 w-4" />
                                <AlertDescription>
                                    This voucher cannot be redeemed (may be expired or already used).
                                </AlertDescription>
                            </Alert>

                            <!-- Voucher Details -->
                            <Card class="mt-4">
                                <CardContent class="space-y-3 pt-6">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-muted-foreground">Code:</span>
                                        <span class="font-mono font-semibold">{{ validatedVoucher.voucher.code }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-muted-foreground">Amount:</span>
                                        <span class="text-lg font-bold text-green-600">
                                            {{ formatAmount(validatedVoucher.voucher.amount, validatedVoucher.voucher.currency) }}
                                        </span>
                                    </div>
                                    <div v-if="validatedVoucher.voucher.expires_at" class="flex justify-between">
                                        <span class="text-sm text-muted-foreground">Expires:</span>
                                        <span class="text-sm">{{ new Date(validatedVoucher.voucher.expires_at).toLocaleDateString() }}</span>
                                    </div>
                                    <div v-if="validatedVoucher.required_validation.secret" class="mt-2 rounded-md bg-yellow-50 p-2">
                                        <p class="text-xs text-yellow-900">
                                            ⚠️ This voucher requires a secret code to redeem
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3">
                            <Button
                                variant="outline"
                                class="flex-1"
                                @click="handleReset"
                            >
                                Try Another
                            </Button>
                            <Button
                                v-if="validatedVoucher?.can_redeem"
                                class="flex-1"
                                @click="handleContinue"
                            >
                                Continue to Redeem
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
