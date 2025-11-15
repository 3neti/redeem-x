<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Head, usePage } from '@inertiajs/vue3';
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
import MerchantNameTemplateComposer from '@/components/MerchantNameTemplateComposer.vue';
import { useToast } from '@/components/ui/toast/use-toast';

const { toast } = useToast();
const page = usePage();

// Load configuration
const config = page.props.loadWalletConfig || {};

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
    merchant_name_template: '{name} - {city}',
    allow_tip: false,
});

const merchantInfo = ref({
    name: '',
    city: '',
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
            amountForm.value.merchant_name_template = m.merchant_name_template || '{name} - {city}';
            amountForm.value.allow_tip = !!m.allow_tip;
            merchantInfo.value.name = m.name || '';
            merchantInfo.value.city = m.city || '';
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
                    :title="config.header?.title || 'Load Your Wallet'"
                    :description="config.header?.show_balance ? `${config.header?.balance_prefix || 'Current Balance:'} ${formattedBalance}` : ''"
                />
            </div>

            <!-- Main Content: QR Display & Share Panel -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Left Column: QR Display -->
                <div v-if="config.qr_card?.show !== false" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ config.qr_card?.title || 'QR Code' }}</CardTitle>
                            <CardDescription>
                                {{ config.qr_card?.description || 'Scan to load.' }}
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
                        <CardFooter v-if="config.qr_card?.show_regenerate_button !== false">
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
                                {{ loading ? (config.qr_card?.regenerate_button_loading_text || 'Generating...') : (config.qr_card?.regenerate_button_text || 'Regenerate QR Code') }}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Right Column: Template + Amount Settings + Share Panel -->
                <div class="space-y-4">
                    <Card v-if="config.display_settings_card?.show !== false">
                        <CardHeader>
                            <CardTitle>{{ config.display_settings_card?.title || 'Display Settings' }}</CardTitle>
                            <CardDescription>{{ config.display_settings_card?.description || 'Customize how your name appears on QR codes' }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <MerchantNameTemplateComposer
                                v-model="amountForm.merchant_name_template"
                                :merchant-name="merchantInfo.name || 'Sample Merchant'"
                                :merchant-city="merchantInfo.city || 'Manila'"
                                :app-name="page.props.appName || 'redeem-x'"
                                @preview="saveAmountSettings"
                            />
                        </CardContent>
                    </Card>

                    <Card v-if="config.amount_settings_card?.show !== false">
                        <CardHeader>
                            <CardTitle>{{ config.amount_settings_card?.title || 'Amount Settings' }}</CardTitle>
                            <CardDescription>{{ config.amount_settings_card?.description || 'These settings control your wallet-load QR behavior' }}</CardDescription>
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
                        <CardFooter v-if="config.amount_settings_card?.show_save_button !== false">
                            <Button :disabled="saving" class="w-full" @click="saveAmountSettings">
                                {{ saving ? (config.amount_settings_card?.save_button_loading_text || 'Saving...') : (config.amount_settings_card?.save_button_text || 'Save Amount Settings') }}
                            </Button>
                        </CardFooter>
                    </Card>

                    <QrSharePanel v-if="config.share_panel?.show !== false" :qr-data="qrData" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
