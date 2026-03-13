<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import { Separator } from '@/components/ui/separator';
import { CheckCircle2, Clock, AlertCircle } from 'lucide-vue-next';
import { computed, ref, onUnmounted } from 'vue';
import { useFormFlowSummary } from '@/composables/useFormFlowSummary';

interface Props {
    flow_id: string;
    state: {
        reference_id: string;
        collected_data: any[];
        completed_at: string;
    };
    callback_triggered: boolean;
}

const props = defineProps<Props>();

// Processing state
const isProcessing = ref(false);
const showError = ref(false);
const errorMessage = ref('');
const elapsedTime = ref(0);
const expectedDuration = 15;
let timerInterval: number | null = null;
let redirectTimeout: number | null = null;

// Detect if this is a disburse flow
const isDisburseFlow = computed(() => props.state.reference_id.startsWith('disburse-'));

// Extract voucher code from reference_id (format: disburse-{CODE}-{timestamp})
const voucherCode = computed(() => {
    if (!isDisburseFlow.value) return null;
    const parts = props.state.reference_id.split('-');
    return parts.slice(1, -1).join('-');
});

// Progress percentage (0-100)
const progress = computed(() => {
    return Math.min((elapsedTime.value / expectedDuration) * 100, 100);
});

// Format elapsed time as MM:SS
const formattedTime = computed(() => {
    const minutes = Math.floor(elapsedTime.value / 60);
    const seconds = elapsedTime.value % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

// Status message based on elapsed time
const statusMessage = computed(() => {
    if (elapsedTime.value < 5) {
        return 'Connecting to payment gateway...';
    } else if (elapsedTime.value < 10) {
        return 'Processing disbursement...';
    } else if (elapsedTime.value < expectedDuration) {
        return 'Waiting for bank confirmation...';
    } else {
        return 'This is taking longer than expected...';
    }
});

function startTimer() {
    elapsedTime.value = 0;
    timerInterval = window.setInterval(() => {
        elapsedTime.value++;
    }, 1000);
}

function stopTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

function handleClose() {
    if (isDisburseFlow.value && voucherCode.value) {
        isProcessing.value = true;
        showError.value = false;
        errorMessage.value = '';
        startTimer();
        
        router.post(`/disburse/${voucherCode.value}/redeem`, {
            flow_id: props.flow_id,
            reference_id: props.state.reference_id,
        }, {
            onError: (errors) => {
                stopTimer();
                showError.value = true;
                errorMessage.value = errors.code || errors.error || 'An error occurred during processing';
                
                redirectTimeout = window.setTimeout(() => {
                    isProcessing.value = false;
                }, 3000);
            },
            onSuccess: () => {
                stopTimer();
                isProcessing.value = false;
            },
        });
    } else {
        window.location.href = '/form-flow-demo.html';
    }
}

// Cleanup on unmount
onUnmounted(() => {
    stopTimer();
    if (redirectTimeout) {
        clearTimeout(redirectTimeout);
    }
});

// Data summary transformation
const { flattenCollectedData, extractHeroData, groupDataBySection } = useFormFlowSummary();

const flatData = computed(() => flattenCollectedData(props.state.collected_data));
const heroData = computed(() => extractHeroData(flatData.value));
const dataSections = computed(() => groupDataBySection(flatData.value));
</script>

<template>
    <Head title="Flow Complete" />

    <!-- ============================================================ -->
    <!-- Disburse Flow: clean, branded layout                         -->
    <!-- ============================================================ -->
    <template v-if="isDisburseFlow">

        <!-- Processing State -->
        <div v-if="isProcessing" class="min-h-screen flex items-center justify-center bg-gradient-to-b from-gray-50 via-white to-gray-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-6">
            <!-- Error -->
            <div v-if="showError" class="w-full max-w-sm text-center space-y-6">
                <AlertCircle class="h-12 w-12 text-destructive mx-auto" />
                <p class="text-lg font-medium">Processing Failed</p>
                <Alert variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>{{ errorMessage }}</AlertDescription>
                </Alert>
                <p class="text-xs text-muted-foreground">Redirecting in 3 seconds…</p>
            </div>

            <!-- Active Processing -->
            <div v-else class="w-full max-w-sm text-center space-y-6">
                <div class="relative mx-auto w-fit">
                    <Spinner class="h-14 w-14 text-amber-500" />
                    <Clock class="h-5 w-5 text-muted-foreground absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
                </div>
                <p class="text-lg font-medium">Processing Redemption</p>
                <p class="text-sm text-muted-foreground">{{ statusMessage }}</p>
                <div class="space-y-1">
                    <div class="h-1 bg-gray-200/60 dark:bg-gray-800 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full bg-amber-400/70 dark:bg-amber-500/50 transition-all duration-1000 ease-linear"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                    <p class="text-[11px] text-muted-foreground font-mono">{{ formattedTime }}</p>
                </div>
                <p v-if="elapsedTime >= expectedDuration" class="text-xs text-muted-foreground">
                    The bank is taking longer than usual. Please keep waiting.
                </p>
            </div>
        </div>

        <!-- Completed / Review State -->
        <div v-else class="min-h-screen bg-gradient-to-b from-amber-50/80 via-white to-gray-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-5 py-8">
            <div class="mx-auto max-w-md space-y-8">

                <!-- Hero: amount + voucher code -->
                <div class="text-center pt-4 space-y-3">
                    <CheckCircle2 class="h-8 w-8 text-green-500 mx-auto" />
                    <p v-if="heroData.amount" class="text-4xl font-bold tracking-tight text-foreground">
                        {{ heroData.amount }}
                    </p>
                    <div v-if="voucherCode" class="inline-flex items-center gap-1.5 px-4 py-1 text-sm font-mono font-semibold tracking-widest text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200/80 dark:border-amber-700/30 rounded-full">
                        <span class="text-amber-400 dark:text-amber-600" aria-hidden="true">||</span>
                        {{ voucherCode }}
                        <span class="text-amber-400 dark:text-amber-600" aria-hidden="true">||</span>
                    </div>
                    <p v-if="heroData.bankName" class="text-sm text-muted-foreground">
                        {{ heroData.bankName }}<template v-if="heroData.settlementRail"> &middot; {{ heroData.settlementRail }}</template>
                    </p>
                </div>

                <!-- Compact summary sections -->
                <div class="space-y-5">
                    <div v-for="section in dataSections" :key="section.title">
                        <p class="text-[11px] uppercase tracking-[0.15em] text-muted-foreground mb-2">{{ section.title }}</p>
                        <div class="space-y-1.5">
                            <div v-for="field in section.fields" :key="field.key" class="flex justify-between items-baseline gap-4">
                                <span class="text-sm text-muted-foreground shrink-0">{{ field.label }}</span>
                                <span class="text-sm font-medium text-foreground text-right truncate">{{ field.value }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirm button -->
                <button
                    @click="handleClose"
                    :disabled="isProcessing"
                    class="inline-flex items-center justify-center w-full h-10 px-6 rounded-full text-sm font-medium transition-all bg-amber-600 hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 text-white shadow-lg shadow-amber-600/20 dark:shadow-amber-500/10 disabled:pointer-events-none disabled:opacity-50"
                >
                    Confirm Redemption
                </button>

                <!-- Reference ID: subtle footer -->
                <p class="text-center text-[10px] text-gray-300 dark:text-gray-700 font-mono">
                    {{ state.reference_id }}
                </p>
            </div>
        </div>
    </template>

    <!-- ============================================================ -->
    <!-- Non-disburse: existing Card layout                           -->
    <!-- ============================================================ -->
    <div v-else class="container mx-auto max-w-2xl px-4 py-8">
        <Card>
            <!-- Processing State -->
            <template v-if="isProcessing">
                <template v-if="showError">
                    <CardHeader class="text-center">
                        <div class="flex justify-center mb-4">
                            <AlertCircle class="h-16 w-16 text-destructive" />
                        </div>
                        <CardTitle class="text-2xl">Processing Failed</CardTitle>
                        <CardDescription>Redirecting back in 3 seconds…</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <Alert variant="destructive">
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>{{ errorMessage }}</AlertDescription>
                        </Alert>
                        <div class="text-center text-sm text-muted-foreground">
                            Processing took {{ formattedTime }}
                        </div>
                    </CardContent>
                </template>

                <template v-else>
                    <CardHeader class="text-center">
                        <div class="flex justify-center mb-4">
                            <div class="relative">
                                <Spinner class="h-16 w-16 text-primary" />
                                <Clock class="h-6 w-6 text-muted-foreground absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
                            </div>
                        </div>
                        <CardTitle class="text-2xl">Processing Redemption</CardTitle>
                        <CardDescription>Please wait while we process your voucher…</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <Alert>
                            <AlertDescription class="text-center">{{ statusMessage }}</AlertDescription>
                        </Alert>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm text-muted-foreground">
                                <span>Elapsed time</span>
                                <span class="font-mono">{{ formattedTime }}</span>
                            </div>
                            <div class="h-2 bg-muted rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-primary transition-all duration-1000 ease-linear"
                                    :style="{ width: `${progress}%` }"
                                />
                            </div>
                        </div>
                        <Alert v-if="elapsedTime >= expectedDuration" variant="default">
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>
                                The bank is taking longer than usual to respond. Please continue waiting or contact support if this persists.
                            </AlertDescription>
                        </Alert>
                    </CardContent>
                </template>
            </template>

            <!-- Completed State -->
            <template v-else>
                <CardHeader class="text-center">
                    <div class="flex justify-center mb-4">
                        <CheckCircle2 class="h-16 w-16 text-green-500" />
                    </div>
                    <CardTitle class="text-2xl">Flow Completed</CardTitle>
                    <CardDescription>Your submission has been received successfully.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="space-y-4">
                        <div v-for="section in dataSections" :key="section.title" class="border rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <component :is="section.icon" class="h-5 w-5 text-muted-foreground" />
                                <h4 class="font-medium">{{ section.title }}</h4>
                            </div>
                            <Separator class="mb-3" />
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="field in section.fields" :key="field.key" class="space-y-1">
                                    <p class="text-sm text-muted-foreground">{{ field.label }}</p>
                                    <p class="text-base font-medium">{{ field.value }}</p>
                                </div>
                            </div>
                        </div>

                        <details class="mt-4">
                            <summary class="text-xs text-muted-foreground cursor-pointer hover:text-foreground">Raw Data</summary>
                            <pre class="text-xs mt-2 p-2 bg-muted rounded overflow-auto">{{ JSON.stringify(state.collected_data, null, 2) }}</pre>
                        </details>
                    </div>

                    <div class="text-center">
                        <p class="text-[10px] text-muted-foreground font-mono">{{ state.reference_id }}</p>
                    </div>

                    <div class="flex justify-center pt-4">
                        <Button @click="handleClose" :disabled="isProcessing">
                            Back to Demo
                        </Button>
                    </div>
                </CardContent>
            </template>
        </Card>
    </div>
</template>
