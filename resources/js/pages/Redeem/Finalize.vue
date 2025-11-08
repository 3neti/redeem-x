<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { confirm } from '@/actions/App/Http/Controllers/Redeem/RedeemController';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle } from 'lucide-vue-next';

interface Props {
    voucher: {
        code: string;
        amount: number;
        currency: string;
        expires_at?: string;
    };
    mobile: string;
    bank_account?: string | null;
    inputs: Record<string, any>;
    has_signature: boolean;
}

const props = defineProps<Props>();

const form = useForm({});

const formattedAmount = computed(() => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: props.voucher.data?.currency || 'PHP',
    }).format(props.voucher.data?.amount || 0);
});

const handleConfirm = () => {
    form.post(confirm.url(props.voucher.data?.code || ''));
};

// Format field names for display
const formatFieldName = (field: string): string => {
    return field
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <Card>
                <CardHeader>
                    <CardTitle>Review & Confirm</CardTitle>
                    <CardDescription>
                        Please review your information before confirming redemption
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Voucher Amount -->
                    <div class="rounded-lg bg-green-50 p-4 text-center">
                        <div class="text-sm text-muted-foreground">You will receive</div>
                        <div class="text-3xl font-bold text-green-600">
                            {{ formattedAmount }}
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="space-y-3">
                        <h3 class="font-semibold">Contact Information</h3>
                        <div class="rounded-md border p-4">
                            <div class="flex justify-between py-2">
                                <span class="text-muted-foreground">Mobile Number:</span>
                                <span class="font-medium">{{ mobile }}</span>
                            </div>
                            <div v-if="bank_account" class="flex justify-between border-t py-2">
                                <span class="text-muted-foreground">Bank Account:</span>
                                <span class="font-medium">{{ bank_account }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div v-if="Object.keys(inputs).length > 0" class="space-y-3">
                        <h3 class="font-semibold">Additional Information</h3>
                        <div class="rounded-md border p-4">
                            <div
                                v-for="(value, key) in inputs"
                                :key="key"
                                class="flex justify-between border-b py-2 last:border-b-0"
                            >
                                <span class="text-muted-foreground">
                                    {{ formatFieldName(String(key)) }}:
                                </span>
                                <span class="max-w-[60%] truncate font-medium" :title="String(value)">
                                    {{ value }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Signature Status -->
                    <div v-if="has_signature" class="flex items-center gap-2 text-green-600">
                        <CheckCircle :size="20" />
                        <span class="font-medium">Signature captured</span>
                    </div>

                    <!-- Confirmation Notice -->
                    <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                        <p class="font-semibold">Important:</p>
                        <p class="mt-1">
                            By confirming, you agree that the information provided is accurate. The
                            cash will be transferred to the provided account or mobile number.
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            class="flex-1"
                            @click="$inertia.visit(`/redeem/${voucher.data?.code}/wallet`)"
                            :disabled="form.processing"
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            class="flex-1"
                            @click="handleConfirm"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Processing...' : 'Confirm Redemption' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
