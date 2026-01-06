<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
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
import QrDisplay from '@/components/shared/QrDisplay.vue';
import VoucherQrSharePanel from '@/components/voucher/VoucherQrSharePanel.vue';
import VoucherMetadataDisplay from '@/components/voucher/VoucherMetadataDisplay.vue';
import VoucherTypeBadge from '@/components/settlement/VoucherTypeBadge.vue';
import VoucherStateBadge from '@/components/settlement/VoucherStateBadge.vue';
import SettlementDetailsCard from '@/components/settlement/SettlementDetailsCard.vue';
import PaymentsCard from '@/components/settlement/PaymentsCard.vue';
import { useVoucherQr } from '@/composables/useVoucherQr';
import { usePage } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import type { VoucherInputFieldOption } from '@/types/voucher';

interface VoucherOwner {
    id: number;
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
    validation?: {
        location?: any;
        time?: any;
    };
    metadata?: any;
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

interface SettlementData {
    type: 'redeemable' | 'payable' | 'settlement';
    state: 'active' | 'locked' | 'closed' | 'cancelled' | 'expired';
    target_amount: number;
    paid_total: number;
    redeemed_total: number;
    remaining: number;
    can_accept_payment: boolean;
    can_redeem: boolean;
    is_locked: boolean;
    is_closed: boolean;
    is_expired: boolean;
    locked_at?: string;
    closed_at?: string;
    rules?: any;
}

interface Props {
    voucher: VoucherProp;
    input_field_options: VoucherInputFieldOption[];
    settlement?: SettlementData;
}

const props = defineProps<Props>();
const page = usePage();

const activeTab = ref<'details' | 'instructions' | 'metadata'>('details');

const hasMetadata = computed(() => !!props.voucher.instructions?.metadata);

const isOwner = computed(() => {
    const currentUser = (page.props as any).auth?.user;
    return currentUser && props.voucher.owner && currentUser.id === props.voucher.owner.id;
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '/vouchers' },
    { title: props.voucher.code, href: '#' },
];

const goBack = () => {
    router.visit('/vouchers');
};

// Generate QR code for voucher (redemption or payment based on type)
const isPayableVoucher = computed(() => 
    props.settlement?.type === 'payable' || props.settlement?.type === 'settlement'
);

const qrPath = computed(() => 
    isPayableVoucher.value ? '/pay' : '/redeem'
);

const { qrData, loading: qrLoading, error: qrError, generateQr } = useVoucherQr(
    props.voucher.code, 
    qrPath.value
);

onMounted(() => {
    // Generate QR for unredeemed/unexpired vouchers, or for payable vouchers that can accept payment
    const shouldGenerateQr = !props.voucher.is_redeemed && !props.voucher.is_expired;
    const canGeneratePaymentQr = isPayableVoucher.value && props.settlement?.can_accept_payment;
    
    if (shouldGenerateQr || canGeneratePaymentQr) {
        generateQr();
    }
});

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
            riderRedirectTimeout: null,
            riderSplash: '',
            riderSplashTimeout: null,
            locationValidation: null,
            timeValidation: null,
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
        riderRedirectTimeout: inst.rider?.redirect_timeout ?? null,
        riderSplash: inst.rider?.splash || '',
        riderSplashTimeout: inst.rider?.splash_timeout ?? null,
        locationValidation: inst.validation?.location || null,
        timeValidation: inst.validation?.time || null,
    };
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <ErrorBoundary>
            <div class="mx-auto max-w-4xl space-y-6 p-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Heading
                                :title="voucher.code"
                                description="Voucher details and status"
                            />
                            <!-- Voucher Type Badge (always show if settlement data exists) -->
                            <VoucherTypeBadge v-if="settlement" :type="settlement.type" />
                            <!-- State Badge (only for payable/settlement types) -->
                            <VoucherStateBadge 
                                v-if="settlement && (settlement.type === 'payable' || settlement.type === 'settlement')" 
                                :state="settlement.state" 
                            />
                        </div>
                    </div>
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
                    :is-settlement="settlement?.type === 'payable' || settlement?.type === 'settlement'"
                    :is-closed="settlement?.is_closed || false"
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
                        <button
                            v-if="hasMetadata"
                            @click="activeTab = 'metadata'"
                            :class="[
                                activeTab === 'metadata'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
                                'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors',
                            ]"
                        >
                            Metadata
                        </button>
                    </nav>
                </div>

                <!-- Details Tab Content -->
                <div v-show="activeTab === 'details'" class="space-y-6">
                    <!-- Settlement Details (for payable/settlement vouchers) -->
                    <SettlementDetailsCard 
                        v-if="settlement && (settlement.type === 'payable' || settlement.type === 'settlement')"
                        :type="settlement.type"
                        :state="settlement.state"
                        :target-amount="settlement.target_amount"
                        :paid-total="settlement.paid_total"
                        :redeemed-total="settlement.redeemed_total"
                        :remaining="settlement.remaining"
                        :currency="voucher.currency"
                    />
                    
                    <!-- Standard Voucher Details -->
                    <VoucherDetailsTabContent 
                        :voucher="voucher" 
                        :redemption="redemptionInputs"
                    />
                    
                    <!-- Payments (for payable/settlement vouchers) -->
                    <PaymentsCard 
                        v-if="settlement && (settlement.type === 'payable' || settlement.type === 'settlement')"
                        :voucher-code="voucher.code"
                        :is-owner="isOwner"
                        :can-accept-payment="settlement.can_accept_payment"
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

                <!-- Metadata Tab Content -->
                <div v-show="activeTab === 'metadata'" v-if="hasMetadata">
                    <VoucherMetadataDisplay 
                        :metadata="voucher.instructions.metadata" 
                        :show-all-fields="true"
                    />
                </div>

                <!-- Owner Information (if available) -->
                <VoucherOwnerView v-if="voucher.owner" :owner="voucher.owner" />

                <!-- QR Code Section -->
                <!-- Show for: unredeemed regular vouchers OR payable vouchers that can accept payment -->
                <div 
                    v-if="(!voucher.is_redeemed && !voucher.is_expired) || (isPayableVoucher && settlement?.can_accept_payment)" 
                    class="grid gap-6 md:grid-cols-2"
                >
                    <!-- QR Display -->
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {{ isPayableVoucher ? 'Payment QR Code' : 'Redemption QR Code' }}
                            </CardTitle>
                            <CardDescription>
                                {{ isPayableVoucher 
                                    ? 'Scan this QR code to make a payment to this voucher' 
                                    : 'Scan this QR code to redeem the voucher' 
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="flex justify-center">
                            <div class="w-full max-w-sm">
                                <QrDisplay
                                    :qr-code="qrData?.qr_code ?? null"
                                    :loading="qrLoading"
                                    :error="qrError"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Share Panel -->
                    <VoucherQrSharePanel
                        :qr-data="qrData"
                        :amount="settlement?.remaining ?? voucher.amount"
                        :currency="voucher.currency"
                    />
                </div>
            </div>
        </ErrorBoundary>
    </AppLayout>
</template>
