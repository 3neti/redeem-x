<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import PwaLayout from '../../../layouts/PwaLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Ticket, Share2, Copy, ArrowLeft, Ban, Clock, Calendar } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useVoucherQr } from '@/composables/useVoucherQr';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '../../../components/ui/dialog';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';
import { useToast } from '@/components/ui/toast/use-toast';
import VoucherStateManager from '../../../components/VoucherStateManager.vue';

interface Props {
    voucher: {
        code: string;
        amount: number;
        target_amount: number | null;
        voucher_type: 'redeemable' | 'payable' | 'settlement';
        currency: string;
        status: string;
        state?: 'active' | 'locked' | 'closed' | 'cancelled' | 'expired';
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
const { toast } = useToast();

// Invalidate dialog
const showInvalidateDialog = ref(false);
const invalidating = ref(false);

// Lock dialog
const showLockDialog = ref(false);
const locking = ref(false);

// Close dialog  
const showCloseDialog = ref(false);
const closing = ref(false);

// Extend expiration dialog
const showExtendDialog = ref(false);
const extending = ref(false);
const extensionType = ref<'hours' | 'days' | 'weeks' | 'months' | 'years' | 'date'>('days');
const extensionValue = ref<number>(1);
const newDate = ref<string>('');

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
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(validNum);
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

const handleInvalidate = () => {
    invalidating.value = true;
    router.post(
        `/pwa/vouchers/${props.voucher.code}/invalidate`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Voucher invalidated',
                    description: `${props.voucher.code} has been invalidated`,
                });
                showInvalidateDialog.value = false;
            },
            onError: (errors) => {
                toast({
                    title: 'Failed to invalidate',
                    description: Object.values(errors).flat().join(', '),
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                invalidating.value = false;
            },
        }
    );
};

const handleLock = () => {
    locking.value = true;
    router.post(
        `/pwa/vouchers/${props.voucher.code}/lock`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Voucher locked',
                    description: `${props.voucher.code} has been locked`,
                });
                showLockDialog.value = false;
            },
            onError: (errors) => {
                toast({
                    title: 'Failed to lock',
                    description: Object.values(errors).flat().join(', '),
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                locking.value = false;
            },
        }
    );
};

const handleClose = () => {
    closing.value = true;
    router.post(
        `/pwa/vouchers/${props.voucher.code}/close`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Voucher closed',
                    description: `${props.voucher.code} has been closed`,
                });
                showCloseDialog.value = false;
            },
            onError: (errors) => {
                toast({
                    title: 'Failed to close',
                    description: Object.values(errors).flat().join(', '),
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                closing.value = false;
            },
        }
    );
};

const handleExtendExpiration = () => {
    extending.value = true;
    
    const payload: any = {
        extension_type: extensionType.value,
    };
    
    if (extensionType.value === 'date') {
        payload.new_date = newDate.value;
    } else {
        payload.extension_value = extensionValue.value;
    }
    
    router.post(
        `/pwa/vouchers/${props.voucher.code}/extend-expiration`,
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Expiration extended',
                    description: `${props.voucher.code} expiration has been updated`,
                });
                showExtendDialog.value = false;
            },
            onError: (errors) => {
                toast({
                    title: 'Failed to extend',
                    description: Object.values(errors).flat().join(', '),
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                extending.value = false;
            },
        }
    );
};

const canManageVoucher = computed(() => {
    // Can manage if not redeemed and not closed
    return !props.voucher.redeemed_at && !props.voucher.closed_at;
});

const getStateColor = (state?: string) => {
    switch (state) {
        case 'active':
            return 'default'; // Blue/primary
        case 'locked':
            return 'secondary'; // Gray/muted (closest to warning)
        case 'closed':
            return 'secondary'; // Gray/muted
        case 'cancelled':
            return 'destructive'; // Red
        case 'expired':
            return 'outline'; // Border only
        default:
            return 'default';
    }
};

const getStateLabel = (state?: string) => {
    switch (state) {
        case 'active':
            return 'Active';
        case 'locked':
            return 'Locked';
        case 'closed':
            return 'Closed';
        case 'cancelled':
            return 'Cancelled';
        case 'expired':
            return 'Expired';
        default:
            return state;
    }
};

const isExpired = computed(() => {
    if (!props.voucher.expires_at) return false;
    return new Date(props.voucher.expires_at) < new Date();
});

const displayState = computed(() => {
    // Priority: Manual states (cancelled, locked, closed) > expired > active
    if (props.voucher.state && props.voucher.state !== 'active') {
        return props.voucher.state; // Show manual state (cancelled, locked, closed)
    }
    // Only show expired if state is still active
    if (isExpired.value) return 'expired';
    return 'active';
});
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
                            <div class="flex gap-2 justify-center mt-2 flex-wrap">
                                <Badge :variant="getVoucherTypeColor(voucher.voucher_type)">
                                    {{ getVoucherTypeLabel(voucher.voucher_type) }}
                                </Badge>
                                <Badge :variant="getStateColor(displayState)">
                                    {{ getStateLabel(displayState) }}
                                </Badge>
                                <Badge v-if="voucher.redeemed_at" variant="default">
                                    Redeemed
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

            <!-- Management Controls -->
            <div v-if="canManageVoucher" class="flex gap-2">
                <VoucherStateManager
                    :voucher-code="voucher.code"
                    :current-state="voucher.state"
                    :can-manage="canManageVoucher"
                    class="flex-1"
                />
                <Button
                    @click="showExtendDialog = true"
                    variant="outline"
                    class="flex-1"
                >
                    <Clock class="mr-2 h-4 w-4" />
                    Extend Expiration
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

        <!-- Invalidate Dialog -->
        <Dialog v-model:open="showInvalidateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invalidate Voucher?</DialogTitle>
                    <DialogDescription>
                        This will mark the voucher as expired immediately. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showInvalidateDialog = false"
                        :disabled="invalidating"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleInvalidate"
                        :disabled="invalidating"
                    >
                        {{ invalidating ? 'Invalidating...' : 'Invalidate' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Extend Expiration Dialog -->
        <Dialog v-model:open="showExtendDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Extend Expiration</DialogTitle>
                    <DialogDescription>
                        Choose how to extend the voucher's expiration date.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Extension Type</Label>
                        <Select v-model="extensionType">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="hours">Hours</SelectItem>
                                <SelectItem value="days">Days</SelectItem>
                                <SelectItem value="weeks">Weeks</SelectItem>
                                <SelectItem value="months">Months</SelectItem>
                                <SelectItem value="years">Years</SelectItem>
                                <SelectItem value="date">Specific Date</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div v-if="extensionType === 'date'" class="space-y-2">
                        <Label>New Expiration Date</Label>
                        <Input
                            type="datetime-local"
                            v-model="newDate"
                            :min="new Date().toISOString().slice(0, 16)"
                        />
                    </div>
                    <div v-else class="space-y-2">
                        <Label>Number of {{ extensionType }}</Label>
                        <Input
                            type="number"
                            v-model="extensionValue"
                            :min="1"
                            placeholder="Enter amount"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showExtendDialog = false"
                        :disabled="extending"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleExtendExpiration"
                        :disabled="extending"
                    >
                        {{ extending ? 'Extending...' : 'Extend' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </PwaLayout>
</template>
