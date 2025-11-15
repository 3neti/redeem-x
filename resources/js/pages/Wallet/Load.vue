<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import { useQrGeneration } from '@/composables/useQrGeneration';
import QrDisplay from '@/components/domain/QrDisplay.vue';
import QrSharePanel from '@/components/QrSharePanel.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { RefreshCcw } from 'lucide-vue-next';
import axios from '@/lib/axios';
import MerchantAmountSettings from '@/components/MerchantAmountSettings.vue';
import { useToast } from '@/components/ui/toast/use-toast';

const { toast } = useToast();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet/load' },
    { title: 'Load', href: '/wallet/load' },
];

const { formattedBalance } = useWalletBalance();

// Use new QR generation composable
const { qrData, loading, error, regenerate } = useQrGeneration(true, 0);

// Amount settings state
const saving = ref(false);
const amountForm = ref({
    is_dynamic: false,
    default_amount: null as number | null,
    min_amount: null as number | null,
    max_amount: null as number | null,
    allow_tip: false,
});

const loadMerchant = async () => {
    try {
        const { data } = await axios.get('/api/v1/merchant/profile');
        if (data.success) {
            const m = data.data.merchant;
            amountForm.value.is_dynamic = !!m.is_dynamic;
            amountForm.value.default_amount = m.default_amount ? parseFloat(m.default_amount) : null;
            amountForm.value.min_amount = m.min_amount ? parseFloat(m.min_amount) : null;
            amountForm.value.max_amount = m.max_amount ? parseFloat(m.max_amount) : null;
            amountForm.value.allow_tip = !!m.allow_tip;
        }
    } catch (e) {
        // ignore
    }
};

const saveAmountSettings = async () => {
    saving.value = true;
    try {
        await axios.put('/api/v1/merchant/profile', amountForm.value);
        toast({ title: 'Saved', description: 'Amount settings updated' });
        await regenerate();
    } catch (e: any) {
        toast({ title: 'Error', description: e.response?.data?.message || 'Failed to save', variant: 'destructive' });
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    loadMerchant();
});
</script>

<template>
    <Head title="Load Wallet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <Heading
                    title="Load Your Wallet"
                    :description="`Current Balance: ${formattedBalance}`"
                />
            </div>

            <!-- Main Content: QR Display & Share Panel -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Left Column: QR Display -->
                <div class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Your QR Code</CardTitle>
                            <CardDescription v-if="qrData?.merchant">
                                Displayed as: <strong>{{ qrData.merchant.name }}{{ qrData.merchant.city ? ' • ' + qrData.merchant.city : '' }}</strong>
                            </CardDescription>
                            <CardDescription v-else>
                                Scan this QR code to load funds
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="flex justify-center">
                            <div class="w-full max-w-sm">
                                <QrDisplay
                                    :qr-code="qrData?.qr_code ?? null"
                                    :loading="loading"
                                    :error="error"
                                />
                            </div>
                        </CardContent>

                        <!-- Regenerate Button -->
                        <CardFooter>
                            <Button
                                class="w-full"
                                variant="outline"
                                :disabled="loading"
                                @click="regenerate"
                            >
                                <RefreshCcw
                                    class="mr-2 h-4 w-4"
                                    :class="{ 'animate-spin': loading }"
                                />
                                {{ loading ? 'Generating...' : 'Regenerate QR Code' }}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Right Column: Amount Settings + Share Panel -->
                <div class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Amount Settings</CardTitle>
                            <CardDescription>These settings control your wallet-load QR behavior</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <MerchantAmountSettings
                                :is-dynamic="amountForm.is_dynamic"
                                :default-amount="amountForm.default_amount"
                                :min-amount="amountForm.min_amount"
                                :max-amount="amountForm.max_amount"
                                :allow-tip="amountForm.allow_tip"
                                @update:isDynamic="(v) => (amountForm.is_dynamic = v)"
                                @update:defaultAmount="(v) => (amountForm.default_amount = v)"
                                @update:minAmount="(v) => (amountForm.min_amount = v)"
                                @update:maxAmount="(v) => (amountForm.max_amount = v)"
                                @update:allowTip="(v) => (amountForm.allow_tip = v)"
                            />
                        </CardContent>
                        <CardFooter>
                            <Button :disabled="saving" class="w-full" @click="saveAmountSettings">
                                {{ saving ? 'Saving...' : 'Save Amount Settings' }}
                            </Button>
                        </CardFooter>
                    </Card>

                    <QrSharePanel :qr-data="qrData" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
