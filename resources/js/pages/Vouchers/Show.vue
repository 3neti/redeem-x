<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import VoucherInstructionsForm from '@/components/voucher/forms/VoucherInstructionsForm.vue';
import { VoucherDetailsTabContent, VoucherOwnerView, VoucherStatusCard } from '@/components/voucher/views';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft } from 'lucide-vue-next';
import ErrorBoundary from '@/components/ErrorBoundary.vue';
import type { BreadcrumbItem } from '@/types';
import type { VoucherInputFieldOption } from '@/types/voucher';

interface VoucherOwner {
    name: string;
    email: string;
}

interface VoucherInstructions {
    cash?: {
        amount: number;
        currency: string;
        validation?: {
            secret?: string;
            mobile?: string;
        };
    };
    inputs?: {
        fields: string[];
    };
    feedback?: {
        email?: string;
        mobile?: string;
        webhook?: string;
    };
    rider?: {
        message?: string;
        url?: string;
    };
    count?: number;
    prefix?: string;
    mask?: string;
    ttl?: string;
}

interface VoucherInput {
    name: string;
    value: string;
}

interface VoucherProp {
    code: string;
    status: string;
    amount: number;
    currency: string;
    created_at: string;
    expires_at?: string;
    redeemed_at?: string;
    starts_at?: string;
    is_expired: boolean;
    is_redeemed: boolean;
    can_redeem: boolean;
    owner?: VoucherOwner;
    instructions?: VoucherInstructions;
    inputs: VoucherInput[];
}

interface RedemptionInputs {
    name?: string;
    email?: string;
    address?: string;
    selfie?: string;
    signature?: string;
    location?: string;
    [key: string]: any;
}

interface Props {
    voucher: VoucherProp;
    input_field_options: VoucherInputFieldOption[];
}

const props = defineProps<Props>();

const activeTab = ref<'details' | 'instructions'>('details');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '/vouchers' },
    { title: props.voucher.code, href: '#' },
];

const goBack = () => {
    router.visit('/vouchers');
};

// Transform voucher inputs (single source of truth) into flat object for display
const redemptionInputs = computed<RedemptionInputs | null>(() => {
    if (!props.voucher.is_redeemed || !props.voucher.inputs || props.voucher.inputs.length === 0) {
        return null;
    }

    // Convert inputs array [{name, value}] to flat object {name: value}
    const inputsObject: RedemptionInputs = {};
    props.voucher.inputs.forEach(input => {
        inputsObject[input.name] = input.value;
    });

    return inputsObject;
});

// Transform instructions for VoucherInstructionsForm
const instructionsFormData = computed(() => {
    const inst = props.voucher.instructions;
    if (!inst) {
        return {
            amount: props.voucher.amount || 0,
            count: 1,
            prefix: '',
            mask: '',
            ttlDays: null,
            selectedInputFields: [],
            validationSecret: '',
            validationMobile: '',
            feedbackEmail: '',
            feedbackMobile: '',
            feedbackWebhook: '',
            riderMessage: '',
            riderUrl: '',
        };
    }

    // Parse TTL from ISO 8601 duration (e.g., P30D)
    let ttlDays = null;
    if (inst.ttl) {
        const match = inst.ttl.match(/P(\d+)D/);
        ttlDays = match ? parseInt(match[1]) : null;
    }

    return {
        amount: inst.cash?.amount || 0,
        count: inst.count || 1,
        prefix: inst.prefix || '',
        mask: inst.mask || '',
        ttlDays,
        selectedInputFields: inst.inputs?.fields || [],
        validationSecret: inst.cash?.validation?.secret || '',
        validationMobile: inst.cash?.validation?.mobile || '',
        feedbackEmail: inst.feedback?.email || '',
        feedbackMobile: inst.feedback?.mobile || '',
        feedbackWebhook: inst.feedback?.webhook || '',
        riderMessage: inst.rider?.message || '',
        riderUrl: inst.rider?.url || '',
    };
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <ErrorBoundary>
            <div class="mx-auto max-w-4xl space-y-6 p-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <Heading
                        :title="voucher.code"
                        description="Voucher details and status"
                    />
                    <Button variant="outline" @click="goBack">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Vouchers
                    </Button>
                </div>

                <!-- Status Card -->
                <VoucherStatusCard
                    :is-redeemed="voucher.is_redeemed"
                    :is-expired="voucher.is_expired"
                    :amount="voucher.amount"
                    :currency="voucher.currency"
                />

                <!-- Tabs Navigation -->
                <div class="border-b">
                    <nav class="flex space-x-8" aria-label="Tabs">
                        <button
                            @click="activeTab = 'details'"
                            :class="[
                                activeTab === 'details'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
                                'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors',
                            ]"
                        >
                            Details
                        </button>
                        <button
                            v-if="voucher.instructions"
                            @click="activeTab = 'instructions'"
                            :class="[
                                activeTab === 'instructions'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
                                'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors',
                            ]"
                        >
                            Instructions
                        </button>
                    </nav>
                </div>

                <!-- Details Tab Content -->
                <div v-show="activeTab === 'details'">
                    <VoucherDetailsTabContent 
                        :voucher="voucher" 
                        :redemption="redemptionInputs"
                    />
                </div>

                <!-- Instructions Tab Content -->
                <div v-show="activeTab === 'instructions'" v-if="voucher.instructions">
                    <VoucherInstructionsForm
                        v-model="instructionsFormData"
                        :input-field-options="input_field_options"
                        :readonly="true"
                        :show-count-field="false"
                    />
                </div>

                <!-- Owner Information (if available) -->
                <VoucherOwnerView v-if="voucher.owner" :owner="voucher.owner" />
            </div>
        </ErrorBoundary>
    </AppLayout>
</template>
