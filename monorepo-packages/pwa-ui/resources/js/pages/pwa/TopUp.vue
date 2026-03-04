<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useToast } from '@/components/ui/toast/use-toast';
import VoucherPaymentCard from '@/components/pwa/VoucherPaymentCard.vue';
import { ArrowLeft, Wallet, Plus, ArrowUpRight, Clock, CheckCircle2, XCircle, AlertCircle, QrCode, RefreshCcw, Download, Share2, Copy } from 'lucide-vue-next';
import QRCode from 'qrcode';

interface TopUpData {
    reference_no: string;
    amount: number;
    status: string;
    gateway: string;
    institution_code?: string;
    created_at: string;
}

interface Props {
    balance: number;
    recentTopUps: TopUpData[];
    pendingTopUps: TopUpData[];
    isSuperAdmin: boolean;
}

const props = defineProps<Props>();
const { toast } = useToast();

// QR Code state
const qrCodeDataUrl = ref<string | null>(null);
const qrLoading = ref(false);
const qrError = ref<string | null>(null);
const qrData = ref<any>(null);

// Form state
const amount = ref<number | null>(null);
const gateway = ref('netbank');
const institutionCode = ref<string>('');
const loading = ref(false);
const error = ref<string | null>(null);

// Quick amount buttons
const quickAmounts = [100, 500, 1000, 2500, 5000];

const formatAmount = (value: number) => {
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
};

// Check if native sharing is supported
const supportsShare = computed(() => {
    return typeof navigator !== 'undefined' && 'share' in navigator;
});

const formatDate = (date: string) => {
    const d = new Date(date);
    const now = new Date();
    const diffMs = now.getTime() - d.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;
    
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const getStatusIcon = (status: string | undefined) => {
    if (!status) return Clock;
    switch (status.toUpperCase()) {
        case 'PAID':
            return CheckCircle2;
        case 'PENDING':
            return Clock;
        case 'FAILED':
        case 'EXPIRED':
            return XCircle;
        default:
            return Clock;
    }
};

const getStatusVariant = (status: string | undefined) => {
    if (!status) return 'outline';
    switch (status.toUpperCase()) {
        case 'PAID':
            return 'success';
        case 'PENDING':
            return 'warning';
        case 'FAILED':
        case 'EXPIRED':
            return 'destructive';
        default:
            return 'outline';
    }
};

const setQuickAmount = (amt: number) => {
    amount.value = amt;
};

// Generate QR Code
const generateQr = async () => {
    qrLoading.value = true;
    qrError.value = null;
    
    try {
        const { data } = await axios.post('/api/v1/wallet/generate-qr', {
            amount: 0, // 0 for dynamic amount
            force: false,
        });
        
        if (data.success && data.data) {
            qrData.value = data.data;
            
            // Check if qr_code is already a data URL (base64 image)
            if (data.data.qr_code && data.data.qr_code.startsWith('data:')) {
                // Already a data URL, use directly
                qrCodeDataUrl.value = data.data.qr_code;
            } else {
                // Generate QR code from shareable_url or qr_url
                const qrContent = data.data.shareable_url || data.data.qr_url;
                if (qrContent) {
                    qrCodeDataUrl.value = await QRCode.toDataURL(qrContent, {
                        width: 300,
                        margin: 2,
                        color: {
                            dark: '#000000',
                            light: '#FFFFFF'
                        }
                    });
                }
            }
        } else {
            qrError.value = 'Failed to generate QR code';
        }
    } catch (e: any) {
        console.error('[QR Generation Error]', e);
        console.error('[QR Response]', e.response?.data);
        qrError.value = e.response?.data?.message || e.message || 'Failed to generate QR code';
    } finally {
        qrLoading.value = false;
    }
};

// Copy QR URL to clipboard
const copyQrUrl = async () => {
    const url = qrData.value?.shareable_url || qrData.value?.qr_url;
    if (url) {
        try {
            await navigator.clipboard.writeText(url);
            toast({
                title: 'Copied!',
                description: 'QR code URL copied to clipboard',
            });
        } catch (e) {
            toast({
                title: 'Error',
                description: 'Failed to copy URL',
                variant: 'destructive',
            });
        }
    }
};

// Share QR code
const shareQr = async () => {
    const url = qrData.value?.shareable_url || qrData.value?.qr_url;
    if (url && navigator.share) {
        try {
            await navigator.share({
                title: 'Load My Wallet',
                text: 'Scan this QR code to send me funds',
                url: url,
            });
        } catch (e) {
            // User cancelled or error
        }
    }
};

// Download QR code as image
const downloadQr = () => {
    if (qrCodeDataUrl.value) {
        // Use merchant display name from API response if available
        const merchantName = qrData.value?.merchant?.display_name;
        const filename = merchantName 
            ? `${merchantName.replace(/[^a-z0-9]/gi, '-').toLowerCase()}.png`
            : `wallet-qr-${Date.now()}.png`;
        
        const link = document.createElement('a');
        link.download = filename;
        link.href = qrCodeDataUrl.value;
        link.click();
        
        toast({
            title: 'Downloaded!',
            description: 'QR code saved to your device',
        });
    }
};

const handleSubmit = async () => {
    if (!amount.value || amount.value < 1) {
        error.value = 'Please enter a valid amount';
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.post('/topup', {
            amount: amount.value,
            gateway: gateway.value,
            institution_code: institutionCode.value || null,
        });

        if (data.success && data.redirect_url) {
            // Redirect to payment gateway
            window.location.href = data.redirect_url;
        } else {
            error.value = data.message || 'Failed to initiate top-up';
            toast({
                title: 'Error',
                description: error.value,
                variant: 'destructive',
            });
        }
    } catch (e: any) {
        error.value = e.response?.data?.message || e.message || 'An error occurred';
        toast({
            title: 'Error',
            description: error.value,
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

// Generate QR on mount
onMounted(() => {
    generateQr();
});
</script>

<template>
    <PwaLayout title="Top-Up">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center gap-3 px-4 py-3">
                <Button variant="ghost" size="icon" @click="router.visit('/pwa/wallet')">
                    <ArrowLeft class="h-5 w-5" />
                </Button>
                <div class="flex-1">
                    <h1 class="text-lg font-semibold">Add Funds</h1>
                    <p class="text-xs text-muted-foreground">Balance: ₱{{ formatAmount(balance) }}</p>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4 space-y-4">
            <!-- Voucher Payment -->
            <VoucherPaymentCard />

            <!-- QR Code Top-Up -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2">
                        <QrCode class="h-5 w-5" />
                        Receive via QR Code
                    </CardTitle>
                    <CardDescription class="text-sm">
                        Share this QR code to receive funds
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- QR Code Display -->
                    <div v-if="qrLoading" class="flex justify-center py-8">
                        <RefreshCcw class="h-8 w-8 animate-spin text-muted-foreground" />
                    </div>
                    
                    <div v-else-if="qrError" class="py-8">
                        <Alert variant="destructive">
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription class="text-sm">{{ qrError }}</AlertDescription>
                        </Alert>
                        <Button variant="outline" class="w-full mt-4" @click="generateQr">
                            <RefreshCcw class="mr-2 h-4 w-4" />
                            Try Again
                        </Button>
                    </div>
                    
                    <div v-else-if="qrCodeDataUrl" class="space-y-4">
                        <!-- Merchant Name -->
                        <div v-if="qrData?.merchant?.display_name" class="text-center">
                            <p class="text-sm font-medium text-foreground">{{ qrData.merchant.display_name }}</p>
                        </div>
                        
                        <!-- QR Image -->
                        <div class="flex justify-center">
                            <img :src="qrCodeDataUrl" alt="QR Code" class="w-64 h-64 border-4 border-border rounded-lg" />
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="grid gap-2">
                            <div class="grid grid-cols-3 gap-2">
                                <Button variant="outline" size="sm" @click="copyQrUrl">
                                    <Copy class="mr-1 h-3 w-3" />
                                    Copy
                                </Button>
                                <Button variant="outline" size="sm" @click="downloadQr">
                                    <Download class="mr-1 h-3 w-3" />
                                    Download
                                </Button>
                                <Button variant="outline" size="sm" @click="shareQr" v-if="supportsShare">
                                    <Share2 class="mr-1 h-3 w-3" />
                                    Share
                                </Button>
                            </div>
                        </div>
                        
                        <Button variant="outline" class="w-full" @click="generateQr" :disabled="qrLoading">
                            <RefreshCcw class="mr-2 h-4 w-4" :class="{ 'animate-spin': qrLoading }" />
                            Regenerate QR Code
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Admin Bank Form (if needed) -->
            <Card v-if="isSuperAdmin" class="hidden">
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2">
                        <Plus class="h-5 w-5" />
                        Bank Top-Up
                        <Badge variant="secondary" class="text-xs">Admin Only</Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Amount Input -->
                    <div class="space-y-2">
                        <Label for="amount">Amount (₱)</Label>
                        <Input
                            id="amount"
                            v-model.number="amount"
                            type="number"
                            placeholder="Enter amount"
                            min="1"
                            max="50000"
                            step="1"
                            inputmode="numeric"
                        />
                    </div>

                    <!-- Quick Amount Buttons -->
                    <div class="space-y-2">
                        <Label>Quick Amounts</Label>
                        <div class="grid grid-cols-3 gap-2">
                            <Button
                                v-for="amt in quickAmounts"
                                :key="amt"
                                variant="outline"
                                size="sm"
                                @click="setQuickAmount(amt)"
                            >
                                ₱{{ formatAmount(amt) }}
                            </Button>
                        </div>
                    </div>

                    <!-- Institution Selection -->
                    <div class="space-y-2">
                        <Label for="institution">Payment Method (Optional)</Label>
                        <select
                            id="institution"
                            v-model="institutionCode"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Any</option>
                            <option value="GCASH">GCash</option>
                            <option value="MAYA">Maya</option>
                            <option value="BDO">BDO</option>
                            <option value="BPI">BPI</option>
                        </select>
                    </div>

                    <!-- Error Alert -->
                    <Alert v-if="error" variant="destructive">
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>{{ error }}</AlertDescription>
                    </Alert>

                    <!-- Submit Button -->
                    <Button
                        class="w-full"
                        :disabled="loading || !amount || amount < 1"
                        @click="handleSubmit"
                    >
                        <ArrowUpRight v-if="!loading" class="mr-2 h-4 w-4" />
                        {{ loading ? 'Processing...' : 'Proceed to Payment' }}
                    </Button>
                </CardContent>
            </Card>

            <!-- Non-Admin Message -->
            <Card v-else>
                <CardHeader>
                    <CardTitle class="text-base">Bank Top-Up Restricted</CardTitle>
                </CardHeader>
                <CardContent>
                    <Alert>
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>
                            Bank-based top-ups are restricted to administrators. Please use voucher payments or contact support.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            <!-- Pending Top-Ups -->
            <Card v-if="pendingTopUps.length > 0">
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2">
                        <Clock class="h-5 w-5" />
                        Pending Payments
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <div
                            v-for="topUp in pendingTopUps"
                            :key="topUp.reference_no"
                            class="flex items-center justify-between p-3 rounded-lg border"
                        >
                            <div>
                                <p class="font-medium text-sm">₱{{ formatAmount(topUp.amount) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ topUp.reference_no }}
                                </p>
                            </div>
                            <Badge :variant="getStatusVariant(topUp.status)" class="text-xs">
                                {{ topUp.status }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Top-Ups -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2">
                        <Wallet class="h-5 w-5" />
                        Recent Top-Ups
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="recentTopUps.length === 0" class="text-center py-8 text-muted-foreground text-sm">
                        No top-ups yet
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="topUp in recentTopUps"
                            :key="topUp.reference_no"
                            class="flex items-center justify-between p-3 rounded-lg border"
                        >
                            <div class="flex items-center gap-3">
                                <component
                                    :is="getStatusIcon(topUp.status)"
                                    class="h-5 w-5 text-muted-foreground"
                                />
                                <div>
                                    <p class="font-medium text-sm">₱{{ formatAmount(topUp.amount) }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatDate(topUp.created_at) }}
                                    </p>
                                </div>
                            </div>
                            <Badge :variant="getStatusVariant(topUp.status)" class="text-xs">
                                {{ topUp.status }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PwaLayout>
</template>
