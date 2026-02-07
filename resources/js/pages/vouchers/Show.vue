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
import ExternalMetadataCard from '@/components/voucher/ExternalMetadataCard.vue';
import VoucherTypeBadge from '@/components/settlement/VoucherTypeBadge.vue';
import VoucherStateBadge from '@/components/settlement/VoucherStateBadge.vue';
import SettlementDetailsCard from '@/components/settlement/SettlementDetailsCard.vue';
import PaymentsCard from '@/components/settlement/PaymentsCard.vue';
import VoucherActionsCard from '@/components/settlement/VoucherActionsCard.vue';
import { 
    EnvelopeStatusCard, 
    EnvelopeChecklistCard, 
    EnvelopeAttachmentsCard,
    EnvelopeSignalsCard,
    EnvelopePayloadCard,
    EnvelopeAuditLog,
    ReasonModal,
    DocumentUploadModal,
    EnvelopeConfigCard 
} from '@/components/envelope';
import type { Envelope, EnvelopeAttachment } from '@/composables/useEnvelope';
import type { DriverSummary, EnvelopeConfig } from '@/types/envelope';
import axios from 'axios';
import { useEnvelopeActions } from '@/composables/useEnvelopeActions';
import { useVoucherQr } from '@/composables/useVoucherQr';
import { usePage } from '@inertiajs/vue3';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle2, XCircle, Info, Lock, Send, Ban, RotateCcw, ThumbsUp, ThumbsDown, Loader2, Upload, PlusCircle } from 'lucide-vue-next';
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
    type: 'payable' | 'redeemable' | 'settlement';
    state: 'active' | 'locked' | 'closed' | 'cancelled' | 'expired';
    target_amount: number;
    paid_total: number;
    redeemed_total: number;
    remaining: number;
    available_balance: number;
    can_accept_payment: boolean;
    can_redeem: boolean;
    is_locked: boolean;
    is_closed: boolean;
    is_expired: boolean;
    locked_at?: string;
    closed_at?: string;
    rules: Record<string, any>;
}

interface Props {
    voucher: VoucherProp;
    input_field_options: VoucherInputFieldOption[];
    settlement?: SettlementData;
    external_metadata?: Record<string, any> | null;
    envelope?: Envelope;
    envelope_drivers?: DriverSummary[];
}

const props = defineProps<Props>();
const page = usePage();

const activeTab = ref<'details' | 'instructions' | 'metadata' | 'envelope'>('details');

// Envelope Actions
const envelopeActions = props.envelope ? useEnvelopeActions(props.voucher.code) : null;

// Modal state for actions requiring reasons
const cancelModalOpen = ref(false);
const reopenModalOpen = ref(false);
const rejectModalOpen = ref(false);
const uploadModalOpen = ref(false);
const rejectingAttachment = ref<EnvelopeAttachment | null>(null);

// Document types from checklist items (for upload modal)
const documentTypes = computed(() => {
    if (!props.envelope?.checklist_items) return [];
    return props.envelope.checklist_items
        .filter(item => item.kind === 'document' && item.doc_type)
        .map(item => ({
            key: item.key,
            label: item.label,
            doc_type: item.doc_type,
            required: item.required,
        }));
});

// Check if uploads are allowed (envelope is editable)
const canUpload = computed(() => {
    return props.envelope?.status_helpers?.can_edit ?? false;
});

// Envelope action handlers
const handleLock = async () => {
    if (!envelopeActions) return;
    const result = await envelopeActions.lock();
    if (result.success) router.reload();
};

const handleSettle = async () => {
    if (!envelopeActions) return;
    const result = await envelopeActions.settle();
    if (result.success) router.reload();
};

const handleCancel = async (reason: string) => {
    if (!envelopeActions) return;
    const result = await envelopeActions.cancel(reason);
    if (result.success) {
        cancelModalOpen.value = false;
        router.reload();
    }
};

const handleReopen = async (reason: string) => {
    if (!envelopeActions) return;
    const result = await envelopeActions.reopen(reason);
    if (result.success) {
        reopenModalOpen.value = false;
        router.reload();
    }
};

const handleSignalToggle = async (key: string, value: boolean) => {
    if (!envelopeActions) return;
    const result = await envelopeActions.setSignal(key, value);
    if (result.success) router.reload();
};

const handleAcceptAttachment = async (attachment: EnvelopeAttachment) => {
    if (!envelopeActions || !props.envelope) return;
    const result = await envelopeActions.acceptAttachment(props.envelope.id, attachment.id);
    if (result.success) router.reload();
};

const openRejectModal = (attachment: EnvelopeAttachment) => {
    rejectingAttachment.value = attachment;
    rejectModalOpen.value = true;
};

const handleRejectAttachment = async (reason: string) => {
    if (!envelopeActions || !props.envelope || !rejectingAttachment.value) return;
    const result = await envelopeActions.rejectAttachment(props.envelope.id, rejectingAttachment.value.id, reason);
    if (result.success) {
        rejectModalOpen.value = false;
        rejectingAttachment.value = null;
        router.reload();
    }
};

const handleUpload = async (docType: string, file: File) => {
    if (!envelopeActions) return;
    const result = await envelopeActions.uploadAttachment(docType, file);
    if (result.success) {
        uploadModalOpen.value = false;
        router.reload();
    }
};

const hasMetadata = computed(() => !!props.voucher.instructions?.metadata);
const hasEnvelope = computed(() => !!props.envelope);

// Envelope creation for vouchers without an envelope
const envelopeConfig = ref<EnvelopeConfig | null>(null);
const creatingEnvelope = ref(false);
const envelopeCreationError = ref<string | null>(null);

const handleCreateEnvelope = async () => {
    if (!envelopeConfig.value?.enabled) return;
    
    creatingEnvelope.value = true;
    envelopeCreationError.value = null;
    
    try {
        await axios.post(`/api/v1/vouchers/${props.voucher.code}/envelope`, {
            driver_id: envelopeConfig.value.driver_id,
            driver_version: envelopeConfig.value.driver_version,
            initial_payload: envelopeConfig.value.initial_payload || {},
            context: { created_via: 'voucher_show' },
        });
        
        // Refresh page to show the new envelope
        router.reload();
    } catch (error: any) {
        envelopeCreationError.value = error.response?.data?.message || 'Failed to create envelope';
    } finally {
        creatingEnvelope.value = false;
    }
};

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

// Generate QR code for voucher redemption only
const isPayableVoucher = computed(() => 
    props.settlement?.type === 'payable' || props.settlement?.type === 'settlement'
);

// Get redemption endpoint from shared props (configured in VoucherSettings)
const redemptionEndpoint = computed(() => (page.props as any).redemption_endpoint || '/disburse');

const { qrData, loading: qrLoading, error: qrError, generateQr } = useVoucherQr(
    props.voucher.code, 
    redemptionEndpoint.value
);

onMounted(() => {
    // Only generate redemption QR for unredeemed/unexpired vouchers
    // Payment QRs are generated separately in /pay page
    const shouldGenerateQr = !props.voucher.is_redeemed && !props.voucher.is_expired;
    
    if (shouldGenerateQr) {
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
    
    // Track location fields for reconstruction
    let latitude: string | null = null;
    let longitude: string | null = null;
    
    props.voucher.inputs.forEach(input => {
        // Special handling for location fields
        if (input.name === 'latitude') {
            latitude = input.value;
        } else if (input.name === 'longitude') {
            longitude = input.value;
        } else if (!['width', 'height', 'format', 'accuracy', 'timestamp', '_step_name', 'viewed_at', 'splash_viewed'].includes(input.name)) {
            // Add non-meta fields to output (selfie, signature, snapshot all use unique names)
            inputsObject[input.name] = input.value;
        }
    });
    
    // Reconstruct location JSON if lat/lng present
    if (latitude && longitude) {
        inputsObject.location = JSON.stringify({
            latitude: parseFloat(latitude),
            longitude: parseFloat(longitude),
            address: {
                formatted: `${latitude}, ${longitude}`
            }
        });
    }

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

                <!-- Flash Messages -->
                <Alert v-if="(page.props.flash as any)?.success" variant="default" class="border-green-500 bg-green-50 dark:bg-green-950">
                    <CheckCircle2 class="h-4 w-4 text-green-600" />
                    <AlertDescription class="text-green-800 dark:text-green-200">
                        {{ (page.props.flash as any).success }}
                    </AlertDescription>
                </Alert>
                <Alert v-if="(page.props.flash as any)?.error" variant="destructive">
                    <XCircle class="h-4 w-4" />
                    <AlertDescription>
                        {{ (page.props.flash as any).error }}
                    </AlertDescription>
                </Alert>
                <Alert v-if="(page.props.flash as any)?.info" variant="default">
                    <Info class="h-4 w-4" />
                    <AlertDescription>
                        {{ (page.props.flash as any).info }}
                    </AlertDescription>
                </Alert>

                <!-- Status Card -->
                <VoucherStatusCard
                    :is-redeemed="voucher.is_redeemed"
                    :is-expired="voucher.is_expired"
                    :amount="settlement?.target_amount ?? voucher.amount"
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
                        <button
                            v-if="hasEnvelope"
                            @click="activeTab = 'envelope'"
                            :class="[
                                activeTab === 'envelope'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
                                'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors',
                            ]"
                        >
                            Envelope
                        </button>
                    </nav>
                </div>

                <!-- Details Tab Content -->
                <div v-show="activeTab === 'details'" class="space-y-6">
                    <!-- Settlement Details (for payable/settlement vouchers) -->
                    <SettlementDetailsCard
                        v-if="settlement && (settlement.type === 'payable' || settlement.type === 'settlement')"
                        :voucher-code="voucher.code"
                        :target-amount="settlement.target_amount"
                        :paid-total="settlement.paid_total"
                        :remaining="settlement.remaining"
                        :available-balance="settlement.available_balance"
                        :state="settlement.state"
                        :currency="voucher.currency"
                        :is-owner="isOwner"
                    />
                    
                    <!-- Standard Voucher Details -->
                    <VoucherDetailsTabContent 
                        :voucher="voucher" 
                        :redemption="redemptionInputs"
                        :voucher-type="settlement?.type"
                        :available-balance="settlement?.available_balance ?? 0"
                    />
                    
                    <!-- External Metadata (if present) -->
                    <ExternalMetadataCard 
                        :metadata="external_metadata"
                        title="Payment Details"
                        description="Reference information for this voucher"
                    />
                    
                    <!-- Payments (for payable/settlement vouchers) -->
                    <PaymentsCard 
                        v-if="settlement && (settlement.type === 'payable' || settlement.type === 'settlement')"
                        :voucher-code="voucher.code"
                        :is-owner="isOwner"
                        :can-accept-payment="settlement.can_accept_payment"
                    />
                    
                    <!-- Admin Actions (owner only) -->
                    <VoucherActionsCard
                        v-if="settlement"
                        :voucher-code="voucher.code"
                        :voucher-state="settlement.state"
                        :voucher-type="settlement.type"
                        :is-owner="isOwner"
                        :is-expired="settlement.is_expired"
                    />
                    
                    <!-- Attach Envelope (when voucher has no envelope) -->
                    <EnvelopeConfigCard
                        v-if="!hasEnvelope && envelope_drivers && envelope_drivers.length > 0"
                        v-model="envelopeConfig"
                        :available-drivers="envelope_drivers"
                        :default-open="false"
                    >
                        <template #footer>
                            <div class="flex items-center justify-between pt-4 border-t">
                                <p v-if="envelopeCreationError" class="text-sm text-destructive">
                                    {{ envelopeCreationError }}
                                </p>
                                <Button
                                    v-if="envelopeConfig?.enabled"
                                    @click="handleCreateEnvelope"
                                    :disabled="creatingEnvelope || !envelopeConfig?.driver_id"
                                    size="sm"
                                >
                                    <Loader2 v-if="creatingEnvelope" class="mr-2 h-4 w-4 animate-spin" />
                                    <PlusCircle v-else class="mr-2 h-4 w-4" />
                                    Attach Envelope
                                </Button>
                            </div>
                        </template>
                    </EnvelopeConfigCard>
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

                <!-- Envelope Tab Content -->
                <div v-show="activeTab === 'envelope'" v-if="hasEnvelope && envelope" class="space-y-6">
                    <EnvelopeStatusCard :envelope="envelope" :show-actions="true">
                        <template #actions="{ canLock, canSettle, canCancel, canReopen, isTerminal }">
                            <div v-if="!isTerminal" class="flex flex-wrap gap-2 pt-4 border-t">
                                <!-- Lock Button -->
                                <Button 
                                    v-if="canLock" 
                                    variant="default" 
                                    size="sm"
                                    @click="handleLock"
                                    :disabled="envelopeActions?.loading.value"
                                >
                                    <Loader2 v-if="envelopeActions?.loading.value" class="mr-2 h-4 w-4 animate-spin" />
                                    <Lock v-else class="mr-2 h-4 w-4" />
                                    Lock Envelope
                                </Button>
                                
                                <!-- Settle Button -->
                                <Button 
                                    v-if="canSettle" 
                                    variant="default" 
                                    size="sm"
                                    @click="handleSettle"
                                    :disabled="envelopeActions?.loading.value"
                                >
                                    <Loader2 v-if="envelopeActions?.loading.value" class="mr-2 h-4 w-4 animate-spin" />
                                    <Send v-else class="mr-2 h-4 w-4" />
                                    Settle
                                </Button>
                                
                                <!-- Cancel Button -->
                                <Button 
                                    v-if="canCancel" 
                                    variant="outline" 
                                    size="sm"
                                    @click="cancelModalOpen = true"
                                    :disabled="envelopeActions?.loading.value"
                                >
                                    <Ban class="mr-2 h-4 w-4" />
                                    Cancel
                                </Button>
                                
                                <!-- Reopen Button -->
                                <Button 
                                    v-if="canReopen" 
                                    variant="outline" 
                                    size="sm"
                                    @click="reopenModalOpen = true"
                                    :disabled="envelopeActions?.loading.value"
                                >
                                    <RotateCcw class="mr-2 h-4 w-4" />
                                    Reopen
                                </Button>
                            </div>
                        </template>
                    </EnvelopeStatusCard>
                    
                    <div class="grid gap-6 md:grid-cols-2">
                        <EnvelopeChecklistCard 
                            v-if="envelope.checklist_items?.length" 
                            :items="envelope.checklist_items" 
                        />
                        <EnvelopeSignalsCard 
                            v-if="envelope.signals?.length" 
                            :signals="envelope.signals"
                            :blocking-signals="envelope.computed_flags?.blocking_signals ?? []"
                            :readonly="!canUpload"
                            @toggle="handleSignalToggle"
                        />
                    </div>
                    
                    <EnvelopeAttachmentsCard 
                        :attachments="envelope.attachments ?? []"
                        :readonly="!canUpload"
                    >
                        <template #upload-action>
                            <Button 
                                v-if="canUpload && documentTypes.length > 0" 
                                variant="outline" 
                                size="sm"
                                @click="uploadModalOpen = true"
                                :disabled="envelopeActions?.loading.value"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                Upload
                            </Button>
                        </template>
                        <template #review-actions="{ attachment, canReview }">
                            <div v-if="canReview" class="flex gap-1">
                                <Button 
                                    variant="ghost" 
                                    size="sm"
                                    class="h-8 w-8 p-0 text-green-600 hover:text-green-700 hover:bg-green-50"
                                    title="Accept"
                                    @click="handleAcceptAttachment(attachment)"
                                    :disabled="envelopeActions?.loading.value"
                                >
                                    <ThumbsUp class="h-4 w-4" />
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="sm"
                                    class="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                                    title="Reject"
                                    @click="openRejectModal(attachment)"
                                    :disabled="envelopeActions?.loading.value"
                                >
                                    <ThumbsDown class="h-4 w-4" />
                                </Button>
                            </div>
                        </template>
                    </EnvelopeAttachmentsCard>
                    
                    <EnvelopePayloadCard 
                        :payload="envelope.payload || {}" 
                        :version="envelope.payload_version"
                        :context="envelope.context"
                        :voucher-code="voucher.code"
                        :readonly="!canUpload"
                    />
                    
                    <EnvelopeAuditLog 
                        v-if="envelope.audit_logs?.length" 
                        :entries="envelope.audit_logs" 
                    />
                </div>

                <!-- Owner Information (if available) -->
                <VoucherOwnerView v-if="voucher.owner" :owner="voucher.owner" />

                <!-- QR Code Section -->
                <!-- Show redemption QR based on voucher type: -->
                <!-- - Payable: Never show (payment-only) -->
                <!-- - Settlement: Show only if available_balance > 0 (can redeem collected funds) -->
                <!-- - Regular: Show if unredeemed and unexpired -->
                <!-- Payment QRs are generated in /pay page, not here -->
                <div 
                    v-if="!voucher.is_redeemed && !voucher.is_expired && settlement?.type !== 'payable' && (!settlement || settlement.available_balance > 0)" 
                    class="grid gap-6 md:grid-cols-2"
                >
                    <!-- QR Display -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Redemption QR Code</CardTitle>
                            <CardDescription>
                                Scan this QR code to redeem the voucher
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
                        :amount="voucher.amount"
                        :currency="voucher.currency"
                    />
                </div>
            </div>
        </ErrorBoundary>
        
        <!-- Cancel Envelope Modal -->
        <ReasonModal
            v-model:open="cancelModalOpen"
            title="Cancel Envelope"
            description="Please provide a reason for cancelling this settlement envelope. This action cannot be undone."
            action="Cancel Envelope"
            variant="destructive"
            :loading="envelopeActions?.loading.value ?? false"
            @confirm="handleCancel"
        />
        
        <!-- Reopen Envelope Modal -->
        <ReasonModal
            v-model:open="reopenModalOpen"
            title="Reopen Envelope"
            description="Please provide a reason for reopening this settlement envelope."
            action="Reopen Envelope"
            :loading="envelopeActions?.loading.value ?? false"
            @confirm="handleReopen"
        />
        
        <!-- Reject Attachment Modal -->
        <ReasonModal
            v-model:open="rejectModalOpen"
            :title="`Reject ${rejectingAttachment?.original_filename ?? 'Attachment'}`"
            description="Please provide a reason for rejecting this attachment."
            action="Reject"
            variant="destructive"
            :loading="envelopeActions?.loading.value ?? false"
            @confirm="handleRejectAttachment"
        />
        
        <!-- Document Upload Modal -->
        <DocumentUploadModal
            v-model:open="uploadModalOpen"
            :document-types="documentTypes"
            :loading="envelopeActions?.loading.value ?? false"
            @upload="handleUpload"
        />
    </AppLayout>
</template>
