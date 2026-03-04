<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import VoucherStatsCard from '@/components/pwa/VoucherStatsCard.vue';
import QuickActionsCard from '@/components/pwa/QuickActionsCard.vue';
import PendingActionsCard from '@/components/pwa/PendingActionsCard.vue';
import KioskView from '@/components/pwa/KioskView.vue';
import { Wallet, Plus, AlertCircle } from 'lucide-vue-next';

interface ScannerConfig {
    enabled: boolean;
    format: string;
    buffer_timeout_ms: number;
    field_mapping: Record<string, string>;
    amount_key: string | null;
    target_amount_key: string | null;
    target_override: boolean;
}

interface SkinConfig {
    title?: string;
    subtitle?: string;
    card_description?: string;
    voucher_type?: string;
    campaign?: string;
    driver?: string;
    amount?: number;
    target_amount?: number;
    inputs?: string[];
    payload?: string[];
    feedback?: string;
    ui?: Record<string, string>;
    scanner?: ScannerConfig | null;
}

interface Props {
    balance: number | string;
    formattedBalance: string;
    currency: string;
    stats: {
        active_vouchers_count: number;
        redeemed_this_month_count: number;
        total_issued_this_month: number;
        formatted_total_issued_this_month: string;
    };
    alerts: Array<{
        type: string;
        message: string;
        action: string;
        action_label: string;
    }>;
    onboarding: {
        hasMobile: boolean;
        hasMerchant: boolean;
        hasBalance: boolean;
        isComplete: boolean;
    };
    // Kiosk props (passed from controller)
    kioskEnabled?: boolean;
    kioskDefaults?: Record<string, string>;
    skinConfig?: SkinConfig | null;
    campaignData?: any;
}

const props = withDefaults(defineProps<Props>(), {
    kioskEnabled: true,
    kioskDefaults: () => ({}),
    skinConfig: null,
    campaignData: null,
});

// ============================================================================
// KIOSK SKIN DETECTION
// ============================================================================

// Get query params from URL
const getQueryParams = () => {
    if (typeof window === 'undefined') return {};
    const params = new URLSearchParams(window.location.search);
    const result: Record<string, string> = {};
    params.forEach((value, key) => {
        result[key] = value;
    });
    return result;
};

const queryParams = getQueryParams();

// Read ?scan= param (JSON string for pre-filling fields)
const initialScan = (() => {
    const raw = queryParams.scan;
    if (!raw) return null;
    try {
        const parsed = JSON.parse(raw);
        return typeof parsed === 'object' && parsed !== null ? parsed : null;
    } catch {
        return null;
    }
})();

// Check if kiosk mode is active
// Supports: ?skin=pos (generic) or ?skin=philhealth-bst (named skin)
const isKiosk = computed(() => {
    if (!props.kioskEnabled) return false;
    if (!queryParams.skin) return false;
    // Either generic 'pos' or a named skin with config loaded
    return queryParams.skin === 'pos' || props.skinConfig !== null;
});

// Parse kiosk config - merges: skinConfig (base) + query params (overrides)
const kioskConfig = computed(() => {
    if (!isKiosk.value) return null;

    const skin = props.skinConfig;
    
    // Build config with priority: query params > skin config > defaults
    return {
        // Identity
        title: queryParams.title || skin?.title || props.kioskDefaults?.title || 'Quick Voucher',
        subtitle: queryParams.subtitle || skin?.subtitle || props.kioskDefaults?.subtitle,
        cardDescription: queryParams.card_description || skin?.card_description,
        
        // Voucher config
        campaign: queryParams.campaign || skin?.campaign,
        driver: queryParams.driver || skin?.driver,
        amount: queryParams.amount !== undefined 
            ? Number(queryParams.amount) 
            : skin?.amount,
        targetAmount: queryParams.target_amount !== undefined 
            ? Number(queryParams.target_amount) 
            : skin?.target_amount,
        
        // Fields
        inputs: queryParams.inputs 
            ? queryParams.inputs.split(',') 
            : (skin?.inputs || []),
        payload: queryParams.payload 
            ? queryParams.payload.split(',') 
            : (skin?.payload || []),
        
        // Callbacks
        feedback: queryParams.feedback || skin?.feedback,
        
        // Type
        type: (queryParams.type || skin?.voucher_type || 'settlement') as 'redeemable' | 'payable' | 'settlement',
        typeLabel: queryParams.type_label || skin?.ui?.type_label,
        
        // UI Labels (query params > skin UI > defaults)
        amountLabel: queryParams.amount_label || skin?.ui?.amount_label,
        amountPlaceholder: skin?.ui?.amount_placeholder,
        amountKeypadTitle: queryParams.amount_keypad_title || skin?.ui?.amount_keypad_title,
        targetLabel: queryParams.target_label || skin?.ui?.target_label,
        targetPlaceholder: skin?.ui?.target_placeholder,
        targetKeypadTitle: queryParams.target_keypad_title || skin?.ui?.target_keypad_title,
        buttonText: queryParams.button_text || skin?.ui?.button_text,
        successTitle: queryParams.success_title || skin?.ui?.success_title,
        successMessage: queryParams.success_message || skin?.ui?.success_message,
        printButton: skin?.ui?.print_button,
        newButton: skin?.ui?.new_button,
        errorTitle: skin?.ui?.error_title,
        retryButton: skin?.ui?.retry_button,
        themeColor: skin?.ui?.theme_color,
        logo: skin?.ui?.logo,
        
        // Scanner config from driver
        scanner: skin?.scanner ?? null,
    };
});
</script>

<template>
    <!-- Kiosk Mode -->
    <KioskView
        v-if="isKiosk && kioskConfig"
        :config="kioskConfig"
        :defaults="kioskDefaults"
        :campaign-data="campaignData"
        :initial-scan="initialScan"
    />

    <!-- Normal Portal -->
    <PwaLayout v-else title="Home">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center justify-between px-4 py-3">
                <div>
                    <h1 class="text-lg font-semibold">Redeem-X</h1>
                    <p class="text-xs text-muted-foreground">Mobile Voucher Platform</p>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4 space-y-4">
            <!-- Wallet Balance Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Wallet class="h-5 w-5 text-primary" />
                            <CardTitle class="text-base">Wallet Balance</CardTitle>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div>
                            <div class="text-3xl font-bold">₱{{ formattedBalance }}</div>
                            <p class="text-sm text-muted-foreground mt-1">Available balance</p>
                        </div>
                        <div class="flex gap-2">
                            <Button as-child class="flex-1">
                                <Link href="/pwa/vouchers/generate">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Generate Voucher
                                </Link>
                            </Button>
                            <Button as-child variant="outline" class="flex-1">
                                <Link href="/pwa/topup">
                                    <Wallet class="mr-2 h-4 w-4" />
                                    Add Funds
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Onboarding Cards -->
            <Card v-if="!onboarding.hasMobile" class="border-orange-200 bg-orange-50">
                <CardHeader class="pb-3">
                    <div class="flex items-start gap-3">
                        <AlertCircle class="h-5 w-5 text-orange-600 mt-0.5" />
                        <div class="flex-1">
                            <CardTitle class="text-base text-orange-900">Complete Your Profile</CardTitle>
                            <CardDescription class="text-orange-700">
                                Add your mobile number to receive notifications
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/settings/profile">Add Mobile Number</Link>
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="!onboarding.hasMerchant" class="border-blue-200 bg-blue-50">
                <CardHeader class="pb-3">
                    <div class="flex items-start gap-3">
                        <AlertCircle class="h-5 w-5 text-blue-600 mt-0.5" />
                        <div class="flex-1">
                            <CardTitle class="text-base text-blue-900">Complete Merchant Profile</CardTitle>
                            <CardDescription class="text-blue-700">
                                Set up your merchant information to start issuing vouchers
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/settings/merchant">Setup Merchant</Link>
                    </Button>
                </CardContent>
            </Card>

            <!-- Voucher Stats -->
            <VoucherStatsCard :stats="stats" />

            <!-- Quick Actions -->
            <QuickActionsCard />

            <!-- Pending Actions -->
            <PendingActionsCard :alerts="alerts" />
        </div>
    </PwaLayout>
</template>
