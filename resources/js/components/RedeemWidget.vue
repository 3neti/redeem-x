<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';
import VoucherInstructionsDisplay from '@/components/voucher/VoucherInstructionsDisplay.vue';
import VoucherMetadataDisplay from '@/components/voucher/VoucherMetadataDisplay.vue';
import VoucherStatusStamp from '@/components/voucher/VoucherStatusStamp.vue';
import OgPreviewCard from '@/components/voucher/OgPreviewCard.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { AlertCircle, Palette, FileText, Download, ChevronDown, Info, Wallet } from 'lucide-vue-next';
import { useVoucherPreview } from '@/composables/useVoucherPreview';
import { useTheme } from '@/composables/useTheme';
import { wallet, start } from '@/actions/App/Http/Controllers/Redeem/RedeemController';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

interface Props {
    showLogo?: boolean;
    showAppName?: boolean;
    showLabel?: boolean;
    showTitle?: boolean;
    showDescription?: boolean;
    title?: string;
    description?: string;
    label?: string;
    placeholder?: string;
    buttonText?: string;
    buttonProcessingText?: string;
    initialCode?: string | null;
    routePrefix?: 'redeem' | 'disburse' | 'pay'; // Support /redeem, /disburse, and /pay
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'quote-loaded': [quote: any]
}>();

const { currentTheme, setTheme, availableThemes } = useTheme();
const page = usePage();
const appName = (page.props.name as string) || 'Redeem-X';
const errors = computed(() => page.props.errors as Record<string, string>);

// Get config from props or fallback to shared config
const config = computed(() => {
    const prefix = props.routePrefix || 'redeem';
    const widgetConfig = prefix === 'pay'
        ? (page.props.pay as any)?.widget || {}
        : (page.props.redeem as any)?.widget || {};
    
    const defaults = prefix === 'pay'
        ? { title: 'Pay Voucher', buttonText: 'Continue to Payment', placeholder: 'x x x x', label: 'code' }
        : { title: 'Redeem Voucher', buttonText: 'Start Redemption', placeholder: 'Enter voucher code', label: 'Voucher Code' };

    return {
        showLogo: widgetConfig.show_logo ?? true,
        showAppName: widgetConfig.show_app_name ?? (prefix !== 'pay'),
        showLabel: widgetConfig.show_label ?? true,
        showTitle: widgetConfig.show_title ?? (prefix !== 'pay'),
        showDescription: widgetConfig.show_description ?? (prefix === 'pay'),
        title: widgetConfig.title ?? defaults.title,
        description: widgetConfig.description ?? null,
        label: widgetConfig.label ?? defaults.label,
        placeholder: widgetConfig.placeholder ?? defaults.placeholder,
        buttonText: widgetConfig.button_text ?? defaults.buttonText,
        buttonProcessingText: widgetConfig.button_processing_text ?? 'Checking...',
    };
});

const form = useForm({
    code: props.initialCode || '',
});

// Voucher preview (destructure refs so v-model gets a plain ref)
const {
    code,
    loading,
    error,
    voucherData,
    showPreview,
    reset: resetPreview,
    hidePreview,
} = useVoucherPreview({ debounceMs: 500, minCodeLength: 4 });

// Initialize preview code with initial code if provided
if (props.initialCode) {
    code.value = props.initialCode;
}

// Computed property for checking if code is valid
const hasValidCode = computed(() => code.value.trim().length > 0);

// Debug logging
onMounted(() => {
    console.log('[RedeemWidget] onMounted called');
    console.log('[RedeemWidget] props.initialCode:', props.initialCode);
    console.log('[RedeemWidget] form.code:', form.code);
    
    // Focus button if code is pre-filled, otherwise focus input
    if (props.initialCode && submitButton.value) {
        // Access the underlying DOM element from the Button component
        const buttonEl = submitButton.value.$el as HTMLElement;
        buttonEl?.focus();
    }
});

const voucherInput = ref<HTMLInputElement | null>(null);
const submitButton = ref<HTMLButtonElement | null>(null);

// Non-active voucher state detection (partially_redeemed stays active — user can still withdraw)
const isNonActive = computed(() => {
    const s = voucherData.value?.status;
    return s === 'redeemed' || s === 'expired';
});

const isPartiallyRedeemed = computed(() => voucherData.value?.status === 'partially_redeemed');
const divisibleInfo = computed(() => voucherData.value?.divisible ?? null);

const statusDate = computed(() => {
    if (!voucherData.value) return null;
    if (voucherData.value.status === 'redeemed') return voucherData.value.redeemed_at;
    if (voucherData.value.status === 'expired') return voucherData.value.expired_at;
    return null;
});

// Check if current device is the redeemer (has persisted wallet data)
const isReturningRedeemer = computed(() => {
    try {
        const raw = localStorage.getItem('form_flow_persist_wallet_info');
        if (!raw) return false;
        const saved = JSON.parse(raw);
        return !!saved.mobile;
    } catch {
        return false;
    }
});

// Envelope data helpers for pay-mode x-ray
const envelopePayload = computed(() => voucherData.value?.envelope?.payload ?? null);
const envelopeAttachments = computed(() => voucherData.value?.envelope?.attachments ?? []);
const hasEnvelopePayload = computed(() => envelopePayload.value && Object.keys(envelopePayload.value).length > 0);
const hasEnvelopeAttachments = computed(() => envelopeAttachments.value.length > 0);

const titleCase = (str: string) => str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

// Render rider splash content (markdown/html/svg)
const renderedSplash = computed(() => {
    const splash = voucherData.value?.instructions?.rider?.splash;
    if (!splash) return null;
    // Detect type and render
    if (splash.trim().startsWith('<svg') || splash.trim().startsWith('<SVG')) {
        return DOMPurify.sanitize(splash);
    }
    if (splash.trim().startsWith('<')) {
        return DOMPurify.sanitize(splash);
    }
    return DOMPurify.sanitize(marked.parse(splash) as string);
});

// Pay-mode loading state (separate from Inertia form.processing)
const payLoading = ref(false);

async function submitPay() {
    const entered = (code.value || form.code || '').trim().toUpperCase();
    if (!entered) return;

    payLoading.value = true;
    try {
        const csrfToken = (page.props as any).csrf_token ||
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const response = await fetch('/pay/quote', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ code: entered }),
        });

        const data = await response.json();

        if (!response.ok) {
            console.log('[RedeemWidget] Pay quote error:', data.error);
            return;
        }

        emit('quote-loaded', data);
    } catch (err: any) {
        console.error('[RedeemWidget] Pay quote fetch error:', err);
    } finally {
        payLoading.value = false;
    }
}

function submit() {
    const prefix = props.routePrefix || 'redeem';

    // Pay mode: fetch quote via API and emit to parent
    if (prefix === 'pay') {
        submitPay();
        return;
    }

    // Use preview code if available, otherwise fall back to form code
    const entered = code.value || form.code;
    form.code = (entered || '').trim().toUpperCase();
    
    // Determine route based on routePrefix prop
    const submitUrl = prefix === 'disburse' ? '/disburse' : start.url();
    
    // Submit to start route which will validate and redirect
    form.get(submitUrl, {
        preserveState: (page) => {
            // Preserve state only if there are no errors
            const hasErrors = Object.keys(page.props.errors || {}).length > 0;
            return !hasErrors;
        },
        preserveScroll: true,
        onError: (errors) => {
            console.log('[RedeemWidget] onError callback:', errors);
        },
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Logo and App Name (hidden for non-active vouchers) -->
        <div v-if="(config.showLogo || config.showAppName) && !isNonActive" class="flex flex-col items-center gap-2">
            <!-- Logo only (icon) -->
            <div v-if="config.showLogo && !config.showAppName" class="flex items-center justify-center">
                <AppLogoIcon class="h-20 w-auto" />
            </div>
            
            <!-- Logo with App Name -->
            <div v-else-if="config.showLogo && config.showAppName" class="flex items-center gap-2">
                <AppLogo />
            </div>
            
            <!-- App Name only (no logo) -->
            <div v-else-if="config.showAppName">
                <span class="text-lg font-semibold">{{ appName }}</span>
            </div>
        </div>

        <!-- Title and Description (hidden for non-active vouchers) -->
        <div v-if="(config.showTitle || config.showDescription) && !isNonActive" class="space-y-2 text-center">
            <h1 v-if="config.showTitle" class="text-xl font-medium">{{ config.title }}</h1>
            <p v-if="config.showDescription && config.description" class="text-center text-sm text-muted-foreground">
                {{ config.description }}
            </p>
        </div>

        <!-- Form (hidden for non-active vouchers) -->
        <form v-if="!isNonActive" @submit.prevent="submit" class="space-y-6">
            <!-- Voucher Code -->
            <div class="flex flex-col gap-2">
                <Label v-if="config.showLabel" for="code">{{ config.label }}</Label>
                <Input
                    id="code"
                    v-model="code"
                    :placeholder="config.placeholder"
                    required
                    autofocus
                    ref="voucherInput"
                    class="text-center text-lg tracking-wider"
                />
                <InputError :message="errors.code" class="mt-1" />
            </div>

            <!-- Submit Button -->
            <Button
                ref="submitButton"
                type="submit"
                :class="routePrefix === 'disburse' ? 'w-full rounded-full' : 'w-full'"
                :disabled="(routePrefix === 'pay' ? payLoading : form.processing) || !hasValidCode"
            >
                {{ (routePrefix === 'pay' ? payLoading : form.processing) ? config.buttonProcessingText : config.buttonText }}
            </Button>
        </form>

        <!-- Voucher Preview -->
        <div v-if="showPreview" :class="isNonActive ? '' : 'mt-6'">
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center gap-2 py-8 text-muted-foreground">
                <Spinner class="h-5 w-5" />
                <span>Checking voucher...</span>
            </div>

            <!-- Error State -->
            <Alert v-else-if="error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    {{ error }}
                </AlertDescription>
            </Alert>

            <!-- Preview disabled notice -->
            <Alert v-else-if="voucherData && voucherData.preview && voucherData.preview.enabled === false">
                <AlertDescription>
                    {{ voucherData.preview.message || 'Preview disabled by issuer.' }}
                </AlertDescription>
            </Alert>

            <!-- Partially Redeemed: compact slice-info card (form stays visible above) -->
            <div v-else-if="voucherData && isPartiallyRedeemed && divisibleInfo" class="space-y-3">
                <Card class="overflow-hidden">
                    <CardContent class="pt-4 pb-4">
                        <div class="text-center space-y-3">
                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-primary/10">
                                <Wallet class="w-5 h-5 text-primary" />
                            </div>
                            <p class="text-2xl font-bold tracking-tight text-foreground">{{ divisibleInfo.formatted_remaining }}</p>
                            <p class="text-sm text-muted-foreground">Remaining balance</p>
                            <div class="space-y-1 pt-1">
                                <div class="flex justify-between text-xs text-muted-foreground">
                                    <span>{{ divisibleInfo.consumed_slices }} of {{ divisibleInfo.max_slices }} withdrawals used</span>
                                    <span>{{ divisibleInfo.remaining_slices }} left</span>
                                </div>
                                <div class="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-primary transition-all duration-500"
                                        :style="{ width: Math.round((divisibleInfo.consumed_slices / divisibleInfo.max_slices) * 100) + '%' }"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Non-Active State: Stamp + Rider Content -->
            <div v-else-if="voucherData && isNonActive" class="space-y-2.5">
                <!-- Status Stamp -->
                <VoucherStatusStamp
                    :status="voucherData.status as 'redeemed' | 'expired'"
                    :status-date="statusDate"
                    :voucher-code="voucherData.code"
                    :formatted-amount="voucherData.instructions?.formatted_amount"
                />

                <!-- Rider Content (only for returning redeemers) -->
                <template v-if="isReturningRedeemer">
                    <!-- Rider Message -->
                    <Card v-if="voucherData.instructions?.rider?.message">
                        <CardContent class="pt-3 pb-3">
                            <p class="text-sm font-medium text-foreground leading-relaxed">
                                {{ voucherData.instructions.rider.message }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Rider Splash -->
                    <Card v-if="renderedSplash">
                        <CardContent class="pt-3 pb-3">
                            <div
                                v-html="renderedSplash"
                                class="prose prose-sm max-w-none dark:prose-invert"
                            />
                        </CardContent>
                    </Card>

                    <!-- OG Link Preview -->
                    <OgPreviewCard
                        v-if="voucherData.instructions?.rider?.url"
                        :url="voucherData.instructions.rider.url"
                        :og-meta="voucherData.og_meta"
                    />
                </template>
            </div>

            <!-- Active State: Pay-mode single-card x-ray -->
            <div v-else-if="voucherData && routePrefix === 'pay'" class="space-y-3">
                <!-- Preview Message -->
                <Alert v-if="voucherData.preview && voucherData.preview.message" variant="default">
                    <AlertDescription>
                        <strong class="font-semibold">Note from issuer:</strong> {{ voucherData.preview.message }}
                    </AlertDescription>
                </Alert>

                <!-- 1. Amount Card -->
                <VoucherInstructionsDisplay
                    v-if="voucherData.instructions"
                    :instructions="voucherData.instructions"
                    :voucher-status="voucherData.status"
                    flow="pay"
                    compact
                />

                <!-- 2. Reference Details -->
                <Card v-if="hasEnvelopePayload">
                    <CardHeader class="pb-2 pt-4 px-4">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Reference Details</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4 pt-0">
                        <div class="space-y-2">
                            <div
                                v-for="(value, key) in envelopePayload"
                                :key="key"
                                class="flex justify-between items-baseline gap-4"
                            >
                                <span class="text-xs text-muted-foreground shrink-0">{{ titleCase(String(key)) }}</span>
                                <span class="text-sm font-medium text-right truncate">{{ value }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- 3. Documents -->
                <Card v-if="hasEnvelopeAttachments">
                    <CardHeader class="pb-2 pt-4 px-4">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Documents</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4 pt-0">
                        <div class="space-y-2">
                            <a
                                v-for="attachment in envelopeAttachments"
                                :key="attachment.id"
                                :href="attachment.url"
                                target="_blank"
                                class="flex items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50 group"
                            >
                                <FileText class="h-4 w-4 text-muted-foreground group-hover:text-primary shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate">{{ attachment.file_name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ attachment.human_readable_size }}</div>
                                </div>
                                <Download class="h-4 w-4 text-muted-foreground group-hover:text-primary shrink-0" />
                            </a>
                        </div>
                    </CardContent>
                </Card>

                <!-- 4. System Info (collapsed by default) -->
                <Collapsible>
                    <CollapsibleTrigger class="flex w-full items-center justify-between rounded-lg border px-4 py-3 text-sm font-medium text-muted-foreground hover:bg-muted/50 transition-colors">
                        <div class="flex items-center gap-2">
                            <Info class="h-4 w-4" />
                            <span>System Info</span>
                        </div>
                        <ChevronDown class="h-4 w-4 transition-transform [[data-state=open]>&]:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent class="pt-3 space-y-3">
                        <VoucherMetadataDisplay
                            :metadata="voucherData.metadata"
                            :show-all-fields="true"
                            compact
                        />

                        <!-- Theme Picker -->
                        <Card>
                            <CardHeader class="pb-3">
                                <div class="flex items-center gap-2">
                                    <Palette class="h-4 w-4 text-primary" />
                                    <CardTitle class="text-sm">Theme</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="theme in availableThemes"
                                        :key="theme.id"
                                        @click="setTheme(theme.id)"
                                        :class="[
                                            'relative rounded-lg border-2 p-2 text-left transition-all',
                                            currentTheme === theme.id
                                                ? 'border-primary ring-1 ring-primary/20'
                                                : 'border-border hover:border-primary/40',
                                        ]"
                                    >
                                        <div class="mb-1.5 flex h-5 gap-0.5 rounded overflow-hidden">
                                            <div :class="[theme.preview.bg, 'flex-1']" />
                                            <div :class="[theme.preview.accent, 'w-2']" />
                                        </div>
                                        <div class="font-medium text-xs">{{ theme.name }}</div>
                                        <div
                                            v-if="currentTheme === theme.id"
                                            class="absolute top-1 right-1 h-3.5 w-3.5 rounded-full bg-primary flex items-center justify-center"
                                        >
                                            <span class="text-primary-foreground text-[8px]">✓</span>
                                        </div>
                                    </button>
                                </div>
                            </CardContent>
                        </Card>
                    </CollapsibleContent>
                </Collapsible>
            </div>

            <!-- Active State: Tabbed preview (redeem/disburse) -->
            <div v-else-if="voucherData">
                <!-- Preview Message (if provided by issuer) -->
                <Alert v-if="voucherData.preview && voucherData.preview.message" class="mb-4" variant="default">
                    <AlertDescription>
                        <strong class="font-semibold">Note from issuer:</strong> {{ voucherData.preview.message }}
                    </AlertDescription>
                </Alert>
                
                <Tabs default-value="instructions">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="instructions">Instructions</TabsTrigger>
                        <TabsTrigger value="system-info">System Info</TabsTrigger>
                    </TabsList>
                    
                    <TabsContent value="instructions" class="mt-4">
                        <VoucherInstructionsDisplay
                            v-if="voucherData.instructions"
                            :instructions="voucherData.instructions"
                            :voucher-status="voucherData.status"
                        />
                        <Alert v-else>
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>
                                This voucher was created before detailed instructions were tracked.
                            </AlertDescription>
                        </Alert>
                    </TabsContent>
                    
                    <TabsContent value="system-info" class="mt-4 space-y-4">
                        <VoucherMetadataDisplay 
                            :metadata="voucherData.metadata"
                            :show-all-fields="true"
                        />

                        <!-- Theme Picker -->
                        <Card v-if="routePrefix === 'disburse'">
                            <CardHeader class="pb-3">
                                <div class="flex items-center gap-2">
                                    <Palette class="h-4 w-4 text-primary" />
                                    <CardTitle class="text-sm">Theme</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="theme in availableThemes"
                                        :key="theme.id"
                                        @click="setTheme(theme.id)"
                                        :class="[
                                            'relative rounded-lg border-2 p-2 text-left transition-all',
                                            currentTheme === theme.id
                                                ? 'border-primary ring-1 ring-primary/20'
                                                : 'border-border hover:border-primary/40',
                                        ]"
                                    >
                                        <div class="mb-1.5 flex h-5 gap-0.5 rounded overflow-hidden">
                                            <div :class="[theme.preview.bg, 'flex-1']" />
                                            <div :class="[theme.preview.accent, 'w-2']" />
                                        </div>
                                        <div class="font-medium text-xs">{{ theme.name }}</div>
                                        <div
                                            v-if="currentTheme === theme.id"
                                            class="absolute top-1 right-1 h-3.5 w-3.5 rounded-full bg-primary flex items-center justify-center"
                                        >
                                            <span class="text-primary-foreground text-[8px]">✓</span>
                                        </div>
                                    </button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    </div>
</template>
