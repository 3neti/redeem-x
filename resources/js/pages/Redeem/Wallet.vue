<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { storeWallet } from '@/actions/App/Http/Controllers/Redeem/RedeemWizardController';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Bank } from '@/types/redemption';

interface Props {
    voucher_code: string;
    voucher: {
        code: string;
        amount: number;
        currency: string;
        expires_at?: string;
    };
    country: string;
    banks: Bank[];
    has_secret: boolean;
}

const props = defineProps<Props>();

const form = useForm({
    mobile: '',
    country: props.country,
    bank_code: '',
    account_number: '',
    secret: '',
});

const formattedAmount = computed(() => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: props.voucher.data?.currency || 'PHP',
    }).format(props.voucher.data?.amount || 0);
});

const handleSubmit = () => {
    form.post(storeWallet.url({ voucher: props.voucher_code }));
};
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <!-- Debug Output -->
            <div class="mb-4 rounded border bg-gray-100 p-4">
                <pre class="text-xs">{{ voucher }}</pre>
            </div>
            
            <!-- Voucher Info Card -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Voucher Details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Code:</span>
                        <span class="font-mono font-semibold">{{ voucher.data?.code }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Amount:</span>
                        <span class="text-lg font-bold text-green-600">{{ formattedAmount }}</span>
                    </div>
                    <div v-if="voucher.data?.expires_at" class="flex justify-between">
                        <span class="text-muted-foreground">Expires:</span>
                        <span>{{ new Date(voucher.data.expires_at).toLocaleDateString() }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Wallet Form Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Contact & Payment Details</CardTitle>
                    <CardDescription>
                        Provide your mobile number and bank account to receive the cash
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- Mobile Number -->
                        <div class="space-y-2">
                            <Label for="mobile">Mobile Number *</Label>
                            <Input
                                id="mobile"
                                v-model="form.mobile"
                                type="tel"
                                placeholder="+63 917 123 4567"
                                required
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.mobile" class="text-sm text-red-600">
                                {{ form.errors.mobile }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Philippine mobile number format: +63 9XX XXX XXXX
                            </p>
                        </div>

                        <!-- Secret (if required) -->
                        <div v-if="has_secret" class="space-y-2">
                            <Label for="secret">Secret Code *</Label>
                            <Input
                                id="secret"
                                v-model="form.secret"
                                type="password"
                                placeholder="Enter secret code"
                                :required="has_secret"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.secret" class="text-sm text-red-600">
                                {{ form.errors.secret }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                This voucher requires a secret code to redeem
                            </p>
                        </div>

                        <!-- Bank Selection -->
                        <div class="space-y-2">
                            <Label for="bank">Bank (Optional)</Label>
                            <select
                                id="bank"
                                v-model="form.bank_code"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="form.processing"
                            >
                                <option value="">Select a bank</option>
                                <option v-for="bank in banks" :key="bank.code" :value="bank.code">
                                    {{ bank.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.bank_code" class="text-sm text-red-600">
                                {{ form.errors.bank_code }}
                            </p>
                        </div>

                        <!-- Account Number -->
                        <div v-if="form.bank_code" class="space-y-2">
                            <Label for="account_number">Account Number *</Label>
                            <Input
                                id="account_number"
                                v-model="form.account_number"
                                type="text"
                                placeholder="Enter account number"
                                :required="!!form.bank_code"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.account_number" class="text-sm text-red-600">
                                {{ form.errors.account_number }}
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex gap-3 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                class="flex-1"
                                @click="$inertia.visit('/redeem')"
                                :disabled="form.processing"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                class="flex-1"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Processing...' : 'Continue' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
