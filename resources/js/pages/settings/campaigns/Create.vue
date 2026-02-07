<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import VoucherInstructionsForm from '@/components/voucher/forms/VoucherInstructionsForm.vue';
import { EnvelopeConfigCard } from '@/components/envelope';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { computed, ref } from 'vue';
import type { VoucherInputFieldOption } from '@/types/voucher';
import type { DriverSummary, EnvelopeConfig } from '@/types/envelope';

interface Props {
    input_field_options: VoucherInputFieldOption[];
    envelope_drivers?: DriverSummary[];
}

const props = defineProps<Props>();

const instructionsFormData = ref({
    amount: 0,
    count: 1,
    prefix: '',
    mask: '',
    ttlDays: null as number | null,
    selectedInputFields: [] as string[],
    validationSecret: '',
    validationMobile: '',
    feedbackEmail: '',
    feedbackMobile: '',
    feedbackWebhook: '',
    riderMessage: '',
    riderUrl: '',
    riderRedirectTimeout: null as number | null,
    riderSplash: '',
    riderSplashTimeout: null as number | null,
    locationValidation: null as any,
    timeValidation: null as any,
    settlementRail: null as string | null,
    feeStrategy: 'absorb' as string,
});

// Envelope configuration - MUST be before useForm
const envelopeConfig = ref<EnvelopeConfig | null>(null);

const form = useForm({
    name: '',
    description: '',
    status: 'draft',
    envelope_config: computed(() => envelopeConfig.value?.enabled ? {
        enabled: true,
        driver_id: envelopeConfig.value.driver_id,
        driver_version: envelopeConfig.value.driver_version,
        initial_payload: envelopeConfig.value.initial_payload || {},
    } : null),
    instructions: computed(() => ({
        cash: {
            amount: instructionsFormData.value.amount,
            currency: 'PHP',
            validation: {
                secret: instructionsFormData.value.validationSecret || null,
                mobile: instructionsFormData.value.validationMobile || null,
                country: 'PH',
                location: null,
                radius: null,
            },
            settlement_rail: instructionsFormData.value.settlementRail || null,
            fee_strategy: instructionsFormData.value.feeStrategy || 'absorb',
        },
        inputs: {
            fields: instructionsFormData.value.selectedInputFields,
        },
        feedback: {
            email: instructionsFormData.value.feedbackEmail || null,
            mobile: instructionsFormData.value.feedbackMobile || null,
            webhook: instructionsFormData.value.feedbackWebhook || null,
        },
        rider: {
            message: instructionsFormData.value.riderMessage || null,
            url: instructionsFormData.value.riderUrl || null,
            redirect_timeout: instructionsFormData.value.riderRedirectTimeout ?? null,
            splash: instructionsFormData.value.riderSplash || null,
            splash_timeout: instructionsFormData.value.riderSplashTimeout ?? null,
        },
        validation: {
            location: instructionsFormData.value.locationValidation || null,
            time: instructionsFormData.value.timeValidation || null,
        },
        count: instructionsFormData.value.count,
        prefix: instructionsFormData.value.prefix || null,
        mask: instructionsFormData.value.mask || null,
        ttl: instructionsFormData.value.ttlDays ? `P${instructionsFormData.value.ttlDays}D` : null,
    })),
});

function submit() {
    form.post('/settings/campaigns');
}
</script>

<template>
    <AppLayout>
        <Head title="Create Campaign" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        title="Create Campaign"
                        description="Create a new voucher campaign template"
                    />
                    <Button variant="outline" as-child>
                        <a href="/settings/campaigns">Cancel</a>
                    </Button>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Info -->
                    <div class="border rounded-lg p-6 space-y-4">
                        <h3 class="font-medium">Basic Information</h3>

                        <div class="space-y-2">
                            <Label for="name">Campaign Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                required
                                placeholder="e.g., Holiday Promo 2024"
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Optional description"
                                rows="3"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>

                    <!-- Voucher Instructions Form -->
                    <VoucherInstructionsForm
                        v-model="instructionsFormData"
                        :input-field-options="input_field_options"
                        :validation-errors="form.errors"
                        :show-count-field="false"
                    />

                    <!-- Settlement Envelope Configuration -->
                    <EnvelopeConfigCard
                        v-if="envelope_drivers && envelope_drivers.length > 0"
                        v-model="envelopeConfig"
                        :available-drivers="envelope_drivers"
                        :default-open="false"
                    />

                    <!-- Actions -->
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            as-child
                            :disabled="form.processing"
                        >
                            <a href="/settings/campaigns">Cancel</a>
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Creating...' : 'Create Campaign' }}
                        </Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
