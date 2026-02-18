<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Wallet, Building2, AlertCircle } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';

interface BankAccount {
    id: string;
    bank_code: string;
    account_number: string;
    label: string | null;
    is_default: boolean;
}

interface Props {
    open: boolean;
    voucherCode: string;
    amount: number;
    paymentRequestId: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'confirmed': [];
}>();

const { toast } = useToast();

// State
const loading = ref(false);
const bankAccounts = ref<BankAccount[]>([]);
const selectedBankAccountId = ref<string>('');
const rememberChoice = ref(false);
const disbursing = ref(false);
const processing = ref(false);
const error = ref<string | null>(null);

// Computed
const hasAccounts = computed(() => bankAccounts.value.length > 0);
const selectedAccount = computed(() => 
    bankAccounts.value.find(acc => acc.id === selectedBankAccountId.value)
);

// Load bank accounts
const loadBankAccounts = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/v1/user/bank-accounts');
        if (data.data?.bank_accounts) {
            bankAccounts.value = data.data.bank_accounts;
            
            // Auto-select default account
            const defaultAccount = bankAccounts.value.find(acc => acc.is_default);
            if (defaultAccount) {
                selectedBankAccountId.value = defaultAccount.id;
            }
        }
    } catch (err: any) {
        console.error('Failed to load bank accounts:', err);
        error.value = 'Failed to load bank accounts';
    } finally {
        loading.value = false;
    }
};

// Transfer to wallet (confirm only)
const handleTransferToWallet = async () => {
    if (processing.value) return;
    
    processing.value = true;
    disbursing.value = true;
    error.value = null;

    try {
        const payload = {
            payment_request_id: props.paymentRequestId,
            voucher_code: props.voucherCode,
            amount: props.amount,
            disburse_now: false,
        };
        
        const { data } = await axios.post('/api/v1/vouchers/confirm-payment', payload);

        if (data.success) {
            toast({
                title: 'Payment Confirmed',
                description: `₱${props.amount.toFixed(2)} transferred to wallet`,
            });
            emit('update:open', false);
            emit('confirmed');
        } else {
            error.value = data.message || 'Failed to confirm payment';
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || err.message || 'Failed to confirm payment';
    } finally {
        disbursing.value = false;
        processing.value = false;
    }
};

// Disburse to bank
const handleDisburseToBank = async () => {
    if (processing.value) return;
    
    if (!selectedBankAccountId.value) {
        error.value = 'Please select a bank account';
        return;
    }
    
    processing.value = true;
    disbursing.value = true;
    error.value = null;

    try {
        const payload = {
            payment_request_id: props.paymentRequestId,
            voucher_code: props.voucherCode,
            amount: props.amount,
            disburse_now: true,
            bank_account_id: selectedBankAccountId.value,
            remember_choice: rememberChoice.value,
        };
        
        const { data } = await axios.post('/api/v1/vouchers/confirm-payment', payload);

        if (data.success) {
            const disbursement = data.data?.disbursement;
            
            if (disbursement?.success) {
                toast({
                    title: 'Payment Confirmed & Disbursed',
                    description: `₱${props.amount.toFixed(2)} sent to bank account`,
                });
                emit('update:open', false);
                emit('confirmed');
            } else {
                error.value = disbursement?.message || 'Disbursement failed. Payment confirmed but funds remain in wallet.';
            }
        } else {
            error.value = data.message || 'Failed to process disbursement';
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || err.message || 'Failed to process disbursement';
    } finally {
        disbursing.value = false;
        processing.value = false;
    }
};

const getBankName = (code: string): string => {
    const names: Record<string, string> = {
        'GXCHPHM2XXX': 'GCash',
        'PYMAPHM2XXX': 'PayMaya',
        'MBTCPHM2XXX': 'Metrobank',
        'BOPIPHMM': 'BPI',
        'BDOPHMMM': 'BDO',
    };
    return names[code] || code;
};

// Watch for open state
watch(() => props.open, (isOpen) => {
    if (isOpen && bankAccounts.value.length === 0) {
        loadBankAccounts();
    }
});
</script>

<template>
    <Sheet :open="open" @update:open="(val) => $emit('update:open', val)">
        <SheetContent side="bottom" class="h-[85vh] flex flex-col">
            <SheetHeader class="flex-shrink-0">
                <SheetTitle>Confirm Payment</SheetTitle>
                <SheetDescription>
                    Choose where to receive the funds
                </SheetDescription>
            </SheetHeader>

            <div class="flex-1 overflow-y-auto py-4 space-y-6">
                <!-- Amount Display -->
                <div class="p-6 bg-primary/10 rounded-lg text-center">
                    <p class="text-sm text-muted-foreground mb-1">Amount</p>
                    <p class="text-4xl font-bold text-primary">
                        ₱{{ amount.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                    </p>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center py-8">
                    <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                </div>

                <!-- No Bank Accounts -->
                <Alert v-else-if="!hasAccounts" class="border-blue-500 bg-blue-50 dark:bg-blue-950">
                    <AlertCircle class="h-4 w-4 text-blue-600" />
                    <AlertDescription class="text-blue-800 dark:text-blue-200">
                        No saved bank accounts. Funds will be transferred to your wallet.
                        <a href="/settings/profile" class="underline font-medium">Add a bank account</a> to enable auto-disbursement.
                    </AlertDescription>
                </Alert>

                <!-- Bank Account Selection -->
                <div v-else class="space-y-4">
                    <div class="space-y-2">
                        <Label for="bank_account">Select Bank Account</Label>
                        <Select v-model="selectedBankAccountId">
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a bank account" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="account in bankAccounts"
                                    :key="account.id"
                                    :value="account.id"
                                >
                                    {{ account.label || getBankName(account.bank_code) }}
                                    • {{ account.account_number }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Remember Choice -->
                    <div class="flex items-center justify-between p-4 border rounded-lg">
                        <div class="flex-1">
                            <Label class="text-base">Remember my choice</Label>
                            <p class="text-xs text-muted-foreground mt-1">
                                Auto-disburse future payments to this account
                            </p>
                        </div>
                        <Switch v-model:checked="rememberChoice" />
                    </div>

                    <!-- Selected Account Info -->
                    <Alert v-if="selectedAccount" class="border-green-500 bg-green-50 dark:bg-green-950">
                        <Building2 class="h-4 w-4 text-green-600" />
                        <AlertDescription class="text-green-800 dark:text-green-200">
                            Funds will be sent to <strong>{{ selectedAccount.label || getBankName(selectedAccount.bank_code) }}</strong>
                            ({{ selectedAccount.account_number }})
                        </AlertDescription>
                    </Alert>
                </div>

                <!-- Error Display -->
                <Alert v-if="error" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>
            </div>

            <!-- Action Buttons (Fixed at bottom) -->
            <div class="flex-shrink-0 flex flex-col gap-3 pt-4 border-t">
                <!-- Transfer to Wallet Button -->
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    @click="handleTransferToWallet"
                    :disabled="processing || disbursing"
                    class="w-full"
                >
                    <Loader2 v-if="disbursing && !selectedAccount" class="h-5 w-5 mr-2 animate-spin" />
                    <Wallet v-else class="h-5 w-5 mr-2" />
                    {{ disbursing && !selectedAccount ? 'Confirming...' : 'Transfer to Wallet' }}
                </Button>

                <!-- Disburse to Bank Button -->
                <Button
                    type="button"
                    size="lg"
                    @click="handleDisburseToBank"
                    :disabled="!hasAccounts || !selectedBankAccountId || processing || disbursing"
                    class="w-full"
                >
                    <Loader2 v-if="disbursing && selectedAccount" class="h-5 w-5 mr-2 animate-spin" />
                    <Building2 v-else class="h-5 w-5 mr-2" />
                    {{ disbursing && selectedAccount ? 'Disbursing...' : 'Disburse to Bank' }}
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
