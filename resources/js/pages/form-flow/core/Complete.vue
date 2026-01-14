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
const expectedDuration = 15; // Expected duration in seconds (matches timeout)
let timerInterval: number | null = null;
let redirectTimeout: number | null = null;

// Detect if this is a disburse flow
const isDisburseFlow = computed(() => props.state.reference_id.startsWith('disburse-'));

// Extract voucher code from reference_id (format: disburse-{CODE}-{timestamp})
const voucherCode = computed(() => {
    if (!isDisburseFlow.value) return null;
    const parts = props.state.reference_id.split('-');
    // Remove 'disburse' prefix and timestamp suffix
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
        // Show processing state
        isProcessing.value = true;
        showError.value = false;
        errorMessage.value = '';
        startTimer();
        
        // POST to redeem endpoint with flow_id and reference_id
        router.post(`/disburse/${voucherCode.value}/redeem`, {
            flow_id: props.flow_id,
            reference_id: props.state.reference_id,
        }, {
            onError: (errors) => {
                // Stop timer and show error
                stopTimer();
                showError.value = true;
                errorMessage.value = errors.code || errors.error || 'An error occurred during processing';
                
                // Wait 3 seconds before allowing redirect
                redirectTimeout = window.setTimeout(() => {
                    isProcessing.value = false;
                }, 3000);
            },
            onSuccess: () => {
                // Success - stop timer and allow immediate redirect
                stopTimer();
                isProcessing.value = false;
            },
        });
    } else {
        // Redirect back to demo or close window
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
const { flattenCollectedData, groupDataBySection } = useFormFlowSummary();
const dataSections = computed(() => {
    const flattened = flattenCollectedData(props.state.collected_data);
    return groupDataBySection(flattened);
});
</script>

<template>
    <Head title="Flow Complete" />

    <div class="container mx-auto max-w-2xl px-4 py-8">
        <Card>
            <!-- Processing State -->
            <template v-if="isProcessing">
                <!-- Error State (during 3-second delay) -->
                <template v-if="showError">
                    <CardHeader class="text-center">
                        <div class="flex justify-center mb-4">
                            <AlertCircle class="h-16 w-16 text-destructive" />
                        </div>
                        <CardTitle class="text-2xl">Processing Failed</CardTitle>
                        <CardDescription>
                            Redirecting back in 3 seconds...
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Error Message -->
                        <Alert variant="destructive">
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>
                                {{ errorMessage }}
                            </AlertDescription>
                        </Alert>
                        
                        <!-- Final time display -->
                        <div class="text-center text-sm text-muted-foreground">
                            Processing took {{ formattedTime }}
                        </div>
                    </CardContent>
                </template>
                
                <!-- Active Processing State -->
                <template v-else>
                    <CardHeader class="text-center">
                        <div class="flex justify-center mb-4">
                            <div class="relative">
                                <Spinner class="h-16 w-16 text-primary" />
                                <Clock class="h-6 w-6 text-muted-foreground absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
                            </div>
                        </div>
                        <CardTitle class="text-2xl">Processing Redemption</CardTitle>
                        <CardDescription>
                            Please wait while we process your voucher...
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Status Message -->
                        <Alert>
                            <AlertDescription class="text-center">
                                {{ statusMessage }}
                            </AlertDescription>
                        </Alert>
                    
                    <!-- Progress Bar -->
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
                    
                        <!-- Warning if taking too long -->
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
                    <CardTitle class="text-2xl">Form Flow Completed!</CardTitle>
                    <CardDescription>
                        Your submission has been received successfully.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Reference ID -->
                    <div class="bg-muted p-4 rounded-lg">
                        <p class="text-sm font-medium text-muted-foreground">Reference ID</p>
                        <p class="text-lg font-mono">{{ state.reference_id }}</p>
                    </div>

                    <!-- Data Summary -->
                    <div class="space-y-4">
                        <h3 class="font-semibold mb-3">Summary</h3>
                        
                        <!-- Section Cards -->
                        <div v-for="section in dataSections" :key="section.title" class="border rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <component :is="section.icon" class="h-5 w-5 text-muted-foreground" />
                                <h4 class="font-medium">{{ section.title }}</h4>
                            </div>
                            
                            <Separator class="mb-3" />
                            
                            <!-- Field Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="field in section.fields" :key="field.key" class="space-y-1">
                                    <p class="text-sm text-muted-foreground">{{ field.label }}</p>
                                    <p class="text-base font-medium">{{ field.value }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Debug Toggle -->
                        <details class="mt-4">
                            <summary class="text-xs text-muted-foreground cursor-pointer hover:text-foreground">View raw data (debug)</summary>
                            <pre class="text-xs mt-2 p-2 bg-muted rounded overflow-auto">{{ JSON.stringify(state.collected_data, null, 2) }}</pre>
                        </details>
                    </div>

                    <!-- Callback Status -->
                    <div v-if="callback_triggered" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <CheckCircle2 class="h-4 w-4 text-green-500" />
                        <span>Callback notification sent</span>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-center pt-4">
                        <Button @click="handleClose" :disabled="isProcessing">
                            {{ isDisburseFlow ? 'Confirm Redemption' : 'Back to Demo' }}
                        </Button>
                    </div>
                </CardContent>
            </template>
        </Card>
    </div>
</template>
