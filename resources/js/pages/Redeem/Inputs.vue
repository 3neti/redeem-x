<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { storePlugin } from '@/actions/App/Http/Controllers/Redeem/RedeemWizardController';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

interface Props {
    voucher_code: string;
    voucher: {
        code: string;
        amount: number;
        currency: string;
    };
    plugin: string;
    requested_fields: string[];
    default_values: Record<string, any>;
}

const props = defineProps<Props>();

// Initialize form with default values
const initialData: Record<string, any> = {};
props.requested_fields.forEach((field) => {
    initialData[field] = props.default_values[field] || '';
});

const form = useForm(initialData);

const handleSubmit = () => {
    form.post(
        storePlugin.url({
            voucher: props.voucher_code,
            plugin: props.plugin,
        })
    );
};

// Field labels and types
const fieldConfig: Record<string, { label: string; type: string; placeholder?: string }> = {
    name: { label: 'Full Name', type: 'text', placeholder: 'Juan Dela Cruz' },
    email: { label: 'Email Address', type: 'email', placeholder: 'juan@example.com' },
    address: { label: 'Full Address', type: 'text', placeholder: '123 Main St, City' },
    birth_date: { label: 'Birth Date', type: 'date' },
    gross_monthly_income: { label: 'Gross Monthly Income', type: 'number', placeholder: '0' },
    location: { label: 'Location', type: 'text', placeholder: 'Current location' },
    reference_code: { label: 'Reference Code', type: 'text', placeholder: 'REF-12345' },
    otp: { label: 'OTP Code', type: 'text', placeholder: '1234' },
};
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <Card>
                <CardHeader>
                    <CardTitle>Additional Information</CardTitle>
                    <CardDescription>
                        Please provide the following information to complete your redemption
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- Dynamic fields based on requested_fields -->
                        <div
                            v-for="field in requested_fields"
                            :key="field"
                            class="space-y-2"
                        >
                            <Label :for="field">
                                {{ fieldConfig[field]?.label || field }} *
                            </Label>
                            <Input
                                :id="field"
                                v-model="form[field]"
                                :type="fieldConfig[field]?.type || 'text'"
                                :placeholder="fieldConfig[field]?.placeholder"
                                required
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors[field]" class="text-sm text-red-600">
                                {{ form.errors[field] }}
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex gap-3 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                class="flex-1"
                                @click="$inertia.visit(`/redeem/${voucher_code}/wallet`)"
                                :disabled="form.processing"
                            >
                                Back
                            </Button>
                            <Button type="submit" class="flex-1" :disabled="form.processing">
                                {{ form.processing ? 'Processing...' : 'Continue' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
