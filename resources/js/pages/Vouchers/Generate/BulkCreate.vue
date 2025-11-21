<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useVoucherApi, type BulkVoucherItem } from '@/composables/useVoucherApi';
import { useCsvVouchers } from '@/composables/useCsvVouchers';
import { useWalletBalance } from '@/composables/useWalletBalance';
import { Download, Upload, Plus, Trash2, AlertCircle, Check, Loader2 } from 'lucide-vue-next';
import axios from 'axios';

interface Campaign {
    id: number;
    name: string;
    slug: string;
    instructions: any;
}

const { balance: walletBalance, formattedBalance } = useWalletBalance();
const { loading, error, bulkCreateVouchers } = useVoucherApi();
const { parseCsv, exportToCsv, downloadCsv, getSampleCsv } = useCsvVouchers();

// Campaign selection
const campaigns = ref<Campaign[]>([]);
const selectedCampaignId = ref<number | null>(null);
const selectedCampaign = ref<Campaign | null>(null);

// Load campaigns
axios.get('/api/v1/campaigns').then(response => {
    campaigns.value = response.data;
}).catch(err => console.error('Failed to load campaigns:', err));

// Watch campaign selection
const loadCampaign = async () => {
    if (!selectedCampaignId.value) {
        selectedCampaign.value = null;
        return;
    }
    
    try {
        const response = await axios.get(`/api/v1/campaigns/${selectedCampaignId.value}`);
        selectedCampaign.value = response.data;
    } catch (err) {
        console.error('Failed to load campaign:', err);
    }
};

// Voucher rows
const vouchers = ref<BulkVoucherItem[]>([
    { mobile: '', external_metadata: { external_id: '', external_type: 'questpay', user_id: '' } }
]);

const addRow = () => {
    if (vouchers.value.length >= 100) {
        alert('Maximum 100 vouchers allowed');
        return;
    }
    vouchers.value.push({
        mobile: '',
        external_metadata: { external_id: '', external_type: 'questpay', user_id: '' }
    });
};

const removeRow = (index: number) => {
    vouchers.value.splice(index, 1);
};

// CSV Import
const fileInput = ref<HTMLInputElement | null>(null);
const handleFileUpload = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target?.result as string;
        const parsed = parseCsv(text);
        if (parsed.length > 100) {
            alert('CSV contains more than 100 rows. Only first 100 will be imported.');
            vouchers.value = parsed.slice(0, 100);
        } else {
            vouchers.value = parsed;
        }
    };
    reader.readAsText(file);
};

const downloadTemplate = () => {
    downloadCsv(getSampleCsv(), 'bulk-vouchers-template.csv');
};

// Cost calculation
const voucherAmount = computed(() => {
    return selectedCampaign.value?.instructions?.cash?.amount || 0;
});

const totalCost = computed(() => {
    return voucherAmount.value * vouchers.value.length;
});

const canAfford = computed(() => {
    return (walletBalance.value ?? 0) >= totalCost.value;
});

// Generation
const generatedVouchers = ref<any[]>([]);
const generationErrors = ref<any[]>([]);
const showResults = ref(false);

const generateBulk = async () => {
    if (!selectedCampaignId.value) {
        alert('Please select a campaign');
        return;
    }

    if (vouchers.value.length === 0) {
        alert('Please add at least one voucher');
        return;
    }

    if (!canAfford.value) {
        alert('Insufficient wallet balance');
        return;
    }

    // Filter out empty rows
    const validVouchers = vouchers.value.filter(v => 
        v.mobile || v.external_metadata?.external_id
    );

    const result = await bulkCreateVouchers({
        campaign_id: selectedCampaignId.value,
        vouchers: validVouchers
    });

    if (result) {
        generatedVouchers.value = result.vouchers;
        generationErrors.value = result.errors || [];
        showResults.value = true;
    }
};

const exportResults = () => {
    if (generatedVouchers.value.length === 0) return;
    const csv = exportToCsv(generatedVouchers.value);
    downloadCsv(csv, `bulk-vouchers-${Date.now()}.csv`);
};

const reset = () => {
    showResults.value = false;
    generatedVouchers.value = [];
    generationErrors.value = [];
    vouchers.value = [{ mobile: '', external_metadata: { external_id: '', external_type: 'questpay', user_id: '' } }];
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                title="Bulk Generate Vouchers"
                description="Generate up to 100 vouchers with unique metadata from a campaign template"
            />

            <!-- Error Alert -->
            <Alert v-if="error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>{{ error.message }}</AlertDescription>
            </Alert>

            <!-- Results -->
            <Card v-if="showResults">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>✅ Generation Complete</CardTitle>
                            <CardDescription>
                                {{ generatedVouchers.length }} vouchers created successfully
                                <span v-if="generationErrors.length > 0" class="text-destructive">
                                    ({{ generationErrors.length }} errors)
                                </span>
                            </CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <Button @click="exportResults" variant="outline">
                                <Download class="mr-2 h-4 w-4" />
                                Export CSV
                            </Button>
                            <Button @click="reset">
                                Generate More
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="generationErrors.length > 0" class="mb-4">
                        <Alert variant="destructive">
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>
                                <div class="font-semibold">Errors:</div>
                                <ul class="mt-2 list-inside list-disc text-sm">
                                    <li v-for="err in generationErrors" :key="err.index">
                                        Row {{ err.index + 1 }}: {{ err.error }}
                                    </li>
                                </ul>
                            </AlertDescription>
                        </Alert>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        Total Amount: ₱{{ totalCost.toFixed(2) }}
                    </div>
                </CardContent>
            </Card>

            <!-- Generation Form -->
            <Card v-if="!showResults">
                <CardHeader>
                    <CardTitle>Campaign Selection</CardTitle>
                    <CardDescription>Choose a campaign as template for bulk generation</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label>Campaign</Label>
                        <select
                            v-model="selectedCampaignId"
                            @change="loadCampaign"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option :value="null">Select a campaign...</option>
                            <option v-for="campaign in campaigns" :key="campaign.id" :value="campaign.id">
                                {{ campaign.name }} (₱{{ campaign.instructions?.cash?.amount || 0 }})
                            </option>
                        </select>
                    </div>

                    <div v-if="selectedCampaign" class="rounded-lg bg-muted p-4">
                        <div class="text-sm">
                            <div><strong>Amount per voucher:</strong> ₱{{ voucherAmount }}</div>
                            <div><strong>Wallet balance:</strong> {{ formattedBalance }}</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- CSV Import -->
            <Card v-if="!showResults && selectedCampaignId">
                <CardHeader>
                    <CardTitle>Import from CSV</CardTitle>
                    <CardDescription>Upload a CSV file or download the template</CardDescription>
                </CardHeader>
                <CardContent class="flex gap-2">
                    <Button @click="downloadTemplate" variant="outline">
                        <Download class="mr-2 h-4 w-4" />
                        Download Template
                    </Button>
                    <Button @click="fileInput?.click()" variant="outline">
                        <Upload class="mr-2 h-4 w-4" />
                        Upload CSV
                    </Button>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv"
                        class="hidden"
                        @change="handleFileUpload"
                    />
                </CardContent>
            </Card>

            <!-- Voucher Table -->
            <Card v-if="!showResults && selectedCampaignId">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Vouchers ({{ vouchers.length }}/100)</CardTitle>
                            <CardDescription>Add vouchers with unique mobile and metadata</CardDescription>
                        </div>
                        <Button @click="addRow" size="sm" :disabled="vouchers.length >= 100">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Row
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b">
                                <tr>
                                    <th class="px-2 py-2 text-left">#</th>
                                    <th class="px-2 py-2 text-left">Mobile</th>
                                    <th class="px-2 py-2 text-left">External ID</th>
                                    <th class="px-2 py-2 text-left">Type</th>
                                    <th class="px-2 py-2 text-left">User ID</th>
                                    <th class="px-2 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(voucher, index) in vouchers" :key="index" class="border-b">
                                    <td class="px-2 py-2">{{ index + 1 }}</td>
                                    <td class="px-2 py-2">
                                        <Input v-model="voucher.mobile" placeholder="09171234567" class="h-8" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <Input v-model="voucher.external_metadata!.external_id" placeholder="quest-001" class="h-8" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <Input v-model="voucher.external_metadata!.external_type" placeholder="questpay" class="h-8" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <Input v-model="voucher.external_metadata!.user_id" placeholder="player-abc" class="h-8" />
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        <Button
                                            @click="removeRow(index)"
                                            variant="ghost"
                                            size="sm"
                                            :disabled="vouchers.length === 1"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary -->
                    <div class="mt-4 flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <div class="text-sm font-medium">Total Cost</div>
                            <div class="text-2xl font-bold">₱{{ totalCost.toFixed(2) }}</div>
                        </div>
                        <Button
                            @click="generateBulk"
                            size="lg"
                            :disabled="loading || !canAfford || vouchers.length === 0"
                        >
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            <Check v-else class="mr-2 h-4 w-4" />
                            Generate {{ vouchers.length }} Voucher{{ vouchers.length > 1 ? 's' : '' }}
                        </Button>
                    </div>

                    <Alert v-if="!canAfford" variant="destructive" class="mt-4">
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>
                            Insufficient wallet balance. Need ₱{{ totalCost.toFixed(2) }}, have {{ formattedBalance }}
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
