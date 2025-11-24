<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

interface Props {
    voucher_code: string;
}

const props = defineProps<Props>();

const storedData = ref<any>(null);
const submitting = ref(false);
const formData = ref<Record<string, any>>({});

// Computed properties to determine next steps
const requiresLocation = computed(() => {
    return (storedData.value?.required_inputs || []).includes('location');
});

const requiresSelfie = computed(() => {
    return (storedData.value?.required_inputs || []).includes('selfie');
});

const requiresSignature = computed(() => {
    return (storedData.value?.required_inputs || []).includes('signature');
});

// Get input fields that should be collected here (excluding location, selfie, signature, kyc)
// KYC is handled separately on the Finalize page, not as a text input
const inputFields = computed(() => {
    return (storedData.value?.required_inputs || [])
        .filter((field: string) => !['location', 'selfie', 'signature', 'kyc'].includes(field));
});

const handleSubmit = async () => {
    try {
        submitting.value = true;

        // Update stored data with collected inputs
        const updatedData = {
            ...storedData.value,
            inputs: {
                ...storedData.value.inputs,
                ...formData.value,
            },
        };

        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(updatedData));

        // Determine next step
        if (requiresLocation.value) {
            router.visit(`/redeem/${props.voucher_code}/location`);
            return;
        }

        if (requiresSelfie.value) {
            router.visit(`/redeem/${props.voucher_code}/selfie`);
            return;
        }

        if (requiresSignature.value) {
            router.visit(`/redeem/${props.voucher_code}/signature`);
            return;
        }

        // No more steps, go to finalize
        router.visit(`/redeem/${props.voucher_code}/finalize`);
    } catch (err: any) {
        submitting.value = false;
        console.error('Navigation failed:', err);
    }
};

// Field labels and types
const fieldConfig: Record<string, { label: string; type: string; placeholder?: string }> = {
    name: { label: 'Full Name', type: 'text', placeholder: 'Juan Dela Cruz' },
    email: { label: 'Email Address', type: 'email', placeholder: 'juan@example.com' },
    address: { label: 'Full Address', type: 'text', placeholder: '123 Main St, City' },
    birth_date: { label: 'Birth Date', type: 'date' },
    gross_monthly_income: { label: 'Gross Monthly Income', type: 'number', placeholder: '0' },
    reference_code: { label: 'Reference Code', type: 'text', placeholder: 'REF-12345' },
    otp: { label: 'OTP Code', type: 'text', placeholder: '1234' },
};

// Load stored data and initialize form when component mounts
onMounted(() => {
    const stored = sessionStorage.getItem(`redeem_${props.voucher_code}`);
    if (!stored) {
        // No stored data, redirect back to wallet
        router.visit(`/redeem/${props.voucher_code}/wallet`);
        return;
    }

    storedData.value = JSON.parse(stored);

    // Initialize form data with empty values for input fields
    inputFields.value.forEach((field: string) => {
        formData.value[field] = '';
    });
});
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <!-- Loading State -->
            <div v-if="!storedData" class="flex min-h-[50vh] items-center justify-center">
                <div class="text-muted-foreground">Loading...</div>
            </div>

            <Card v-else>
                <CardHeader>
                    <CardTitle>Additional Information</CardTitle>
                    <CardDescription>
                        Please provide the following information to complete your redemption
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- Dynamic fields based on required inputs -->
                        <div
                            v-for="field in inputFields"
                            :key="field"
                            class="space-y-2"
                        >
                            <Label :for="field">
                                {{ fieldConfig[field]?.label || field }} *
                            </Label>
                            <Input
                                :id="field"
                                v-model="formData[field]"
                                :type="fieldConfig[field]?.type || 'text'"
                                :placeholder="fieldConfig[field]?.placeholder"
                                required
                                :disabled="submitting"
                            />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex gap-3 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                class="flex-1"
                                @click="router.visit(`/redeem/${props.voucher_code}/wallet`)"
                                :disabled="submitting"
                            >
                                Back
                            </Button>
                            <Button type="submit" class="flex-1" :disabled="submitting">
                                {{ submitting ? 'Processing...' : 'Continue' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
