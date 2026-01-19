<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle, ExternalLink } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import VoucherMetadataDisplay from '@/components/voucher/VoucherMetadataDisplay.vue';
import { useTemplateProcessor } from '@/composables/useTemplateProcessor';

interface Props {
    // From API flow
    voucher_code?: string;
    amount?: number;
    currency?: string;
    mobile?: string;
    message?: string;
    // From controller flow
    voucher?: {
        code: string;
        amount: number;
        currency: string;
        contact?: {
            mobile: string;
            bank_code?: string;
            account_number?: string;
            bank_account?: string;
        };
    };
    rider?: {
        message?: string;
        url?: string;
    };
    metadata?: any;
    config: any;
}

const props = defineProps<Props>();

// Computed values that work for both flows
const voucherCode = computed(() => props.voucher_code || props.voucher?.code);
const voucherAmount = computed(() => props.amount || props.voucher?.amount || 0);
const voucherCurrency = computed(() => props.currency || props.voucher?.currency || 'PHP');

const redirectTimeout = computed(() => props.config?.redirect?.timeout ?? 10);
const countdown = ref(redirectTimeout.value);
const isRedirecting = ref(false);

const formattedAmount = computed(() => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: voucherCurrency.value,
    }).format(voucherAmount.value);
});

const hasRiderUrl = computed(() => {
    return !!props.rider?.url;
});

// Process rider URL through template processor to evaluate variables
const processedRiderUrl = computed(() => {
    if (!props.rider?.url) return null;
    return processTemplate(props.rider.url);
});

// Initialize template processor with props context and custom formatters
const { processTemplate } = useTemplateProcessor(props, {
    formatters: {
        // Format 'amount' with currency
        'amount': () => formattedAmount.value,
    },
    fallback: '', // Return empty string for missing values
});

const displayMessage = computed(() => {
    const rawMessage = props.rider?.message || props.message || props.config?.instruction?.default_message || 'Thank you for redeeming your voucher! The cash will be transferred shortly.';
    return processTemplate(rawMessage);
});

const countdownMessage = computed(() => {
    const template = props.config?.redirect?.countdown_message || 'You will be redirected in {seconds} seconds...';
    // Use simple replace for {seconds} since it's a dynamic countdown value
    return template.replace('{seconds}', countdown.value.toString());
});

const processedFooterNote = computed(() => {
    const template = props.config?.footer_note || 'The cash has been transferred to your account. You should receive a confirmation message shortly.';
    return processTemplate(template);
});

// Process all text fields through template processor
const processedTitle = computed(() => processTemplate(props.config?.confirmation?.title || 'Redemption Successful!'));
const processedSubtitle = computed(() => processTemplate(props.config?.confirmation?.subtitle || 'Your voucher has been redeemed'));
const processedAppName = computed(() => processTemplate(props.config?.app_name || 'Redeem'));
const processedCodeLabel = computed(() => processTemplate(props.config?.voucher_details?.code_label || 'Voucher Code'));
const processedAmountLabel = computed(() => processTemplate(props.config?.voucher_details?.amount_label || 'Amount Received'));
const processedMobileLabel = computed(() => processTemplate(props.config?.voucher_details?.mobile_label || 'Mobile Number'));
const processedButtonText = computed(() => processTemplate(props.config?.redirect?.button_text || 'Continue Now'));
const processedRedirectingMessage = computed(() => processTemplate(props.config?.redirect?.redirecting_message || 'Redirecting...'));
const processedRedeemAnotherText = computed(() => processTemplate(props.config?.actions?.redeem_another_text || 'Redeem Another Voucher'));

const instructionStyleClasses = computed(() => {
    const style = props.config?.instruction?.style || 'prominent';
    if (style === 'prominent') {
        return 'text-2xl font-bold text-foreground';
    } else if (style === 'highlighted') {
        return 'text-xl font-semibold text-foreground';
    }
    return 'text-base font-medium text-foreground';
});

const redirectStyleClasses = computed(() => {
    const style = props.config?.redirect?.style || 'subtle';
    if (style === 'prominent') {
        return 'text-base font-semibold';
    } else if (style === 'normal') {
        return 'text-sm font-medium';
    }
    return 'text-xs';
});

const handleRedirect = () => {
    if (!hasRiderUrl.value) return;
    isRedirecting.value = true;
    // Use Inertia router to visit redirect route (which handles external redirects properly)
    router.visit(`/redeem/${voucherCode.value}/redirect`);
};

onMounted(() => {
    // Start countdown if rider URL exists, redirect is enabled, and timeout > 0
    // If timeout is 0, only manual button redirect is allowed (no auto-redirect)
    if (hasRiderUrl.value && props.rider.url && props.config?.show_redirect !== false && redirectTimeout.value > 0) {
        const interval = setInterval(() => {
            countdown.value--;

            if (countdown.value <= 0) {
                clearInterval(interval);
                handleRedirect();
            }
        }, 1000);

        // Cleanup on unmount
        return () => clearInterval(interval);
    }
});
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Redemption Successful" />
        <div class="w-full max-w-md space-y-4">
            <!-- Logo and App Name -->
            <div v-if="config?.show_logo || config?.show_app_name" class="flex flex-col items-center gap-2">
                <AppLogo v-if="config?.show_logo" />
                <span v-if="config?.show_app_name" class="text-lg font-medium">{{ processedAppName }}</span>
            </div>

            <!-- Success Confirmation -->
            <div v-if="config?.show_success_confirmation" class="flex flex-col items-center gap-3">
                <div v-if="config?.confirmation?.show_icon" class="rounded-full bg-green-100 p-3">
                    <CheckCircle :size="48" class="text-green-600" />
                </div>
                <h1 v-if="config?.confirmation?.show_title" class="text-2xl font-bold text-center">
                    {{ processedTitle }}
                </h1>
                <p v-if="config?.confirmation?.show_subtitle" class="text-sm text-muted-foreground text-center">
                    {{ processedSubtitle }}
                </p>
            </div>

            <!-- Advertisement - before instruction -->
            <div v-if="config?.show_advertisement && config?.advertisement?.position === 'before-instruction'" 
                 :class="config?.advertisement?.show_as_card ? '' : 'p-4'">
                <Card v-if="config?.advertisement?.show_as_card">
                    <CardContent class="pt-6" v-html="config?.advertisement?.content"></CardContent>
                </Card>
                <div v-else v-html="config?.advertisement?.content"></div>
            </div>

            <!-- Instruction Message (PROMINENT) -->
            <div v-if="config?.show_instruction_message" 
                 :class="config?.instruction?.show_as_card ? '' : 'p-4 text-center'">
                <Card v-if="config?.instruction?.show_as_card">
                    <CardContent class="pt-6 text-center">
                        <p :class="instructionStyleClasses">{{ displayMessage }}</p>
                    </CardContent>
                </Card>
                <p v-else :class="instructionStyleClasses">{{ displayMessage }}</p>
            </div>

            <!-- Advertisement - after instruction -->
            <div v-if="config?.show_advertisement && config?.advertisement?.position === 'after-instruction'" 
                 :class="config?.advertisement?.show_as_card ? '' : 'p-4'">
                <Card v-if="config?.advertisement?.show_as_card">
                    <CardContent class="pt-6" v-html="config?.advertisement?.content"></CardContent>
                </Card>
                <div v-else v-html="config?.advertisement?.content"></div>
            </div>

            <!-- Voucher Details (Compact, factual) -->
            <div v-if="config?.show_voucher_details" 
                 :class="config?.voucher_details?.show_as_card ? '' : (config?.voucher_details?.style === 'compact' ? 'space-y-1.5' : 'space-y-3')">
                <Card v-if="config?.voucher_details?.show_as_card" :class="config?.voucher_details?.style === 'compact' ? 'border-muted/60' : ''">
                    <CardContent :class="config?.voucher_details?.style === 'compact' ? 'py-3 px-4 space-y-1.5' : 'pt-6 space-y-3'">
                        <div v-if="config?.voucher_details?.show_amount" class="flex justify-between items-baseline">
                            <span class="text-xs text-muted-foreground">{{ processedAmountLabel }}:</span>
                            <span class="text-base font-semibold text-green-600">{{ formattedAmount }}</span>
                        </div>
                        <div v-if="config?.voucher_details?.show_code" class="flex justify-between items-baseline">
                            <span class="text-xs text-muted-foreground">{{ processedCodeLabel }}:</span>
                            <span class="font-mono text-xs">{{ voucherCode }}</span>
                        </div>
                        <div v-if="config?.voucher_details?.show_mobile && mobile" class="flex justify-between items-baseline">
                            <span class="text-xs text-muted-foreground">{{ processedMobileLabel }}:</span>
                            <span class="text-xs">{{ mobile }}</span>
                        </div>
                    </CardContent>
                </Card>
                <div v-else :class="config?.voucher_details?.style === 'compact' ? 'space-y-1.5' : 'space-y-3'">
                    <div v-if="config?.voucher_details?.show_amount" class="flex justify-between items-baseline">
                        <span class="text-xs text-muted-foreground">{{ processedAmountLabel }}:</span>
                        <span class="text-base font-semibold text-green-600">{{ formattedAmount }}</span>
                    </div>
                    <div v-if="config?.voucher_details?.show_code" class="flex justify-between items-baseline">
                        <span class="text-xs text-muted-foreground">{{ processedCodeLabel }}:</span>
                        <span class="font-mono text-xs">{{ voucherCode }}</span>
                    </div>
                    <div v-if="config?.voucher_details?.show_mobile && mobile" class="flex justify-between items-baseline">
                        <span class="text-xs text-muted-foreground">{{ processedMobileLabel }}:</span>
                        <span class="text-xs">{{ mobile }}</span>
                    </div>
                </div>
            </div>

            <!-- Metadata Display -->
            <div v-if="config?.show_metadata && metadata && config?.metadata?.position === 'after-details'">
                <VoucherMetadataDisplay 
                    :metadata="metadata" 
                    :compact="config?.metadata?.compact ?? true"
                    :show-all-fields="false"
                />
            </div>

            <!-- Advertisement - after details -->
            <div v-if="config?.show_advertisement && config?.advertisement?.position === 'after-details'" 
                 :class="config?.advertisement?.show_as_card ? '' : 'p-4'">
                <Card v-if="config?.advertisement?.show_as_card">
                    <CardContent class="pt-6" v-html="config?.advertisement?.content"></CardContent>
                </Card>
                <div v-else v-html="config?.advertisement?.content"></div>
            </div>

            <!-- Redirect/Countdown (Subtle) -->
            <div v-if="config?.show_redirect && hasRiderUrl && !isRedirecting" class="space-y-3">
                <!-- Only show countdown if timeout > 0 (auto-redirect enabled) -->
                <div v-if="config?.redirect?.show_countdown && redirectTimeout > 0" class="text-center" :class="redirectStyleClasses">
                    <p class="text-muted-foreground">
                        {{ countdownMessage }}
                    </p>
                </div>
                <Button v-if="config?.redirect?.show_manual_button" 
                        variant="outline" 
                        class="w-full" 
                        @click="handleRedirect">
                    {{ processedButtonText }}
                    <ExternalLink :size="16" class="ml-2" />
                </Button>
            </div>

            <!-- Redirecting state -->
            <div v-else-if="hasRiderUrl && isRedirecting" class="text-center">
                <p class="text-sm text-muted-foreground">
                    {{ processedRedirectingMessage }}
                </p>
            </div>

            <!-- Action Buttons (when no redirect) -->
            <div v-else-if="config?.show_action_buttons && !hasRiderUrl" class="space-y-2">
                <Button v-if="config?.actions?.show_redeem_another" 
                        class="w-full" 
                        @click="router.visit('/redeem')">
                    {{ processedRedeemAnotherText }}
                </Button>
            </div>

            <!-- Advertisement - bottom -->
            <div v-if="config?.show_advertisement && config?.advertisement?.position === 'bottom'" 
                 :class="config?.advertisement?.show_as_card ? '' : 'p-4'">
                <Card v-if="config?.advertisement?.show_as_card">
                    <CardContent class="pt-6" v-html="config?.advertisement?.content"></CardContent>
                </Card>
                <div v-else v-html="config?.advertisement?.content"></div>
            </div>

            <!-- Footer Note -->
            <div v-if="config?.show_footer_note" class="text-center">
                <p class="text-xs text-muted-foreground">
                    {{ processedFooterNote }}
                </p>
            </div>
        </div>
    </div>
</template>
