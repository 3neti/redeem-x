<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PwaLayout from '../../../layouts/PwaLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Ticket, Share2, Copy, ArrowLeft } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useVoucherQr } from '@/composables/useVoucherQr';

interface Props {
    voucher: {
        code: string;
        amount: number;
        target_amount: number | null;
        voucher_type: 'redeemable' | 'payable' | 'settlement';
        currency: string;
        status: string;
        created_at: string;
        starts_at: string | null;
        expires_at: string | null;
        redeemed_at: string | null;
        locked_at: string | null;
        closed_at: string | null;
        redeem_url: string;
    };
}

const props = defineProps<Props>();
const copied = ref(false);
const page = usePage();

// Get redemption endpoint from shared props (configured in VoucherSettings)
const redemptionEndpoint = computed(() => 
    (page.props as any).redemption_endpoint || '/disburse'
);

// QR Code generation - use dynamic endpoint based on voucher type
const getRedemptionPath = () => {
    // Use voucher-type specific endpoints
    switch (props.voucher.voucher_type) {
        case 'payable':
        case 'settlement':
            return (page.props as any).settlement_endpoint || '/pay';
        default:
            return redemptionEndpoint.value;
    }
};

const { qrData, loading: qrLoading, error: qrError, generateQr } = useVoucherQr(
    props.voucher.code, 
    getRedemptionPath()
);

// Generate QR on mount
onMounted(() => {
    generateQr();
});

// Check if Web Share API is available (client-side only)
const canShare = computed(() => {
    return typeof navigator !== 'undefined' && 'share' in navigator;
});

const copyToClipboard = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const shareVoucher = async () => {
    if (navigator.share) {
        try {
            await navigator.share({
                title: `Voucher ${props.voucher.code}`,
                text: `Redeem this voucher: ${props.voucher.code}`,
                url: props.voucher.redeem_url,
            });
        } catch (err) {
            console.error('Share failed:', err);
        }
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatAmount = (amount: number | string | null | undefined) => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    const validNum = typeof num === 'number' && !isNaN(num) ? num : 0;
    return validNum.toFixed(2);
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'redeemed':
            return 'success';
        case 'pending':
            return 'warning';
        default:
            return 'default';
    }
};

const getVoucherTypeColor = (type: string) => {
    switch (type) {
        case 'payable':
            return 'default'; // Blue/gray
        case 'settlement':
            return 'secondary'; // Purple/muted
        case 'redeemable':
        default:
            return 'outline'; // Border only
    }
};

const getVoucherTypeLabel = (type: string) => {
    switch (type) {
        case 'payable':
            return 'Payable';
        case 'settlement':
            return 'Settlement';
        case 'redeemable':
        default:
            return 'Redeemable';
    }
};
</script>

<template>
    <PwaLayout :title="`Voucher ${voucher.code}`">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center gap-3 px-4 py-3">
                <Button as-child variant="ghost" size="icon">
                    <Link href="/pwa/vouchers">
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-lg font-semibold">Voucher Details</h1>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4 space-y-4">
            <!-- Voucher Card -->
            <Card>
                <CardContent class="pt-6">
                    <div class="text-center space-y-4">
                        <div class="inline-flex p-4 bg-primary/10 rounded-full">
                            <Ticket class="h-12 w-12 text-primary" />
                        </div>
                        <div>
                            <div class="text-2xl font-bold">{{ voucher.code }}</div>
                            <div class="flex gap-2 justify-center mt-2">
                                <Badge :variant="getVoucherTypeColor(voucher.voucher_type)">
                                    {{ getVoucherTypeLabel(voucher.voucher_type) }}
                                </Badge>
                                <Badge v-if="voucher.status" :variant="getStatusColor(voucher.status)">
                                    {{ voucher.status }}
                                </Badge>
                            </div>
                        </div>
                        <div class="py-4 border-y">
                            <div v-if="voucher.voucher_type === 'settlement' && voucher.target_amount" class="space-y-2">
                                <div class="text-sm text-muted-foreground">Loan Amount</div>
                                <div class="text-2xl font-bold">
                                    {{ voucher.currency }} {{ formatAmount(voucher.amount) }}
                                </div>
                                <div class="text-sm text-muted-foreground">Payback Amount</div>
                                <div class="text-2xl font-bold text-primary">
                                    {{ voucher.currency }} {{ formatAmount(voucher.target_amount) }}
                                </div>
                            </div>
                            <div v-else>
                                <div v-if="voucher.voucher_type === 'payable'" class="text-sm text-muted-foreground mb-1">
                                    Target Amount
                                </div>
                                <div class="text-3xl font-bold">
                                    {{ voucher.currency }} {{ formatAmount(voucher.amount) }}
                                </div>
                            </div>
                        </div>
                        <div class="text-sm text-muted-foreground space-y-1">
                            <div>Created: {{ formatDate(voucher.created_at) }}</div>
                            <div v-if="voucher.starts_at">
                                Starts: {{ formatDate(voucher.starts_at) }}
                            </div>
                            <div v-if="voucher.expires_at">
                                Expires: {{ formatDate(voucher.expires_at) }}
                            </div>
                            <div v-if="voucher.redeemed_at">
                                Redeemed: {{ formatDate(voucher.redeemed_at) }}
                            </div>
                            <div v-if="voucher.locked_at">
                                Locked: {{ formatDate(voucher.locked_at) }}
                            </div>
                            <div v-if="voucher.closed_at">
                                Closed: {{ formatDate(voucher.closed_at) }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Actions -->
            <div class="space-y-2">
                <Button
                    @click="copyToClipboard(voucher.code)"
                    variant="outline"
                    class="w-full"
                >
                    <Copy class="mr-2 h-4 w-4" />
                    {{ copied ? 'Copied!' : 'Copy Code' }}
                </Button>
                <Button
                    v-if="canShare"
                    @click="shareVoucher"
                    variant="outline"
                    class="w-full"
                >
                    <Share2 class="mr-2 h-4 w-4" />
                    Share Voucher
                </Button>
                <Button
                    @click="copyToClipboard(voucher.redeem_url)"
                    variant="outline"
                    class="w-full"
                >
                    <Copy class="mr-2 h-4 w-4" />
                    Copy Redeem Link
                </Button>
            </div>

            <!-- QR Code -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm">Scan to Redeem</CardTitle>
                    <CardDescription class="text-xs">
                        Show this QR code to the redeemer
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="qrLoading" class="flex items-center justify-center py-12">
                        <div class="text-sm text-muted-foreground">Generating QR code...</div>
                    </div>
                    <div v-else-if="qrError" class="flex items-center justify-center py-12">
                        <div class="text-sm text-destructive">{{ qrError }}</div>
                    </div>
                    <div v-else-if="qrData" class="flex flex-col items-center">
                        <!-- QR Code Image -->
                        <img 
                            :src="qrData.qr_code" 
                            alt="Redemption QR Code"
                            class="w-64 h-64 rounded-lg border"
                        />
                        <!-- URL Text (small, below QR) -->
                        <div class="mt-4 text-xs text-muted-foreground text-center break-all">
                            {{ qrData.redemption_url }}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PwaLayout>
</template>
