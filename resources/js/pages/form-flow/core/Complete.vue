<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle2 } from 'lucide-vue-next';
import { computed } from 'vue';

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

// Detect if this is a disburse flow
const isDisburseFlow = computed(() => props.state.reference_id.startsWith('disburse-'));

// Extract voucher code from reference_id (format: disburse-{CODE}-{timestamp})
const voucherCode = computed(() => {
    if (!isDisburseFlow.value) return null;
    const parts = props.state.reference_id.split('-');
    // Remove 'disburse' prefix and timestamp suffix
    return parts.slice(1, -1).join('-');
});

function handleClose() {
    if (isDisburseFlow.value && voucherCode.value) {
        // POST to redeem endpoint with flow_id and reference_id
        router.post(`/disburse/${voucherCode.value}/redeem`, {
            flow_id: props.flow_id,
            reference_id: props.state.reference_id,
        });
    } else {
        // Redirect back to demo or close window
        window.location.href = '/form-flow-demo.html';
    }
}
</script>

<template>
    <Head title="Flow Complete" />

    <div class="container mx-auto max-w-2xl px-4 py-8">
        <Card>
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

                <!-- Collected Data Summary -->
                <div>
                    <h3 class="font-semibold mb-3">Collected Data</h3>
                    <div class="space-y-2">
                        <div v-for="(data, index) in state.collected_data" :key="index" class="bg-muted/50 p-3 rounded">
                            <p class="text-sm font-medium text-muted-foreground mb-2">Step {{ index + 1 }}</p>
                            <pre class="text-xs overflow-auto">{{ JSON.stringify(data, null, 2) }}</pre>
                        </div>
                    </div>
                </div>

                <!-- Callback Status -->
                <div v-if="callback_triggered" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <CheckCircle2 class="h-4 w-4 text-green-500" />
                    <span>Callback notification sent</span>
                </div>

                <!-- Actions -->
                <div class="flex justify-center pt-4">
                    <Button @click="handleClose">
                        {{ isDisburseFlow ? 'Confirm Redemption' : 'Back to Demo' }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
