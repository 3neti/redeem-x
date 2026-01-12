<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from '@/lib/axios';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Info, Wallet, Building2 } from 'lucide-vue-next';

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
    paymentRequestId?: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'confirmed': [];
}>();

// State
const loading = ref(false);
const bankAccounts = ref<BankAccount[]>([]);
const selectedBankAccountId = ref<string>('');
const rememberChoice = ref(false);
const disbursing = ref(false);
const processing = ref(false); // Prevent double-clicks
const error = ref<string | null>(null);

// Computed
const hasAccounts = computed(() => bankAccounts.value.length > 0);
const selectedAccount = computed(() => 
    bankAccounts.value.find(acc => acc.id === selectedBankAccountId.value)
);

// Methods
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

const handleTransferToWallet = async () => {
    if (processing.value) {
        console.log('[AutoDisbursementModal] Already processing, ignoring click');
        return;
    }
    
    console.log('[AutoDisbursementModal] Transfer to Wallet clicked', {
        paymentRequestId: props.paymentRequestId,
        voucherCode: props.voucherCode,
        amount: props.amount,
    });
    
    processing.value = true;
    disbursing.value = true;
    error.value = null;

    try {
        const payload = {
            payment_request_id: props.paymentRequestId,
            voucher_code: props.voucherCode,
            amount: props.amount,
            disburse_now: false, // Just confirm, don't disburse
        };
        
        console.log('[AutoDisbursementModal] Sending confirm-payment request', payload);
        const { data } = await axios.post('/api/v1/vouchers/confirm-payment', payload);

        console.log('[AutoDisbursementModal] Transfer to Wallet response', data);
        
        if (data.success) {
            console.log('[AutoDisbursementModal] Success - closing modal');
            emit('update:open', false);
            emit('confirmed');
        } else {
            console.log('[AutoDisbursementModal] Failed -', data.message);
            error.value = data.message || 'Failed to confirm payment';
        }
    } catch (err: any) {
        console.error('[AutoDisbursementModal] Confirmation error:', err);
        error.value = err.response?.data?.message || err.message || 'Failed to confirm payment';
    } finally {
        disbursing.value = false;
        processing.value = false;
    }
};

const handleDisburseToBank = async () => {
    if (processing.value) {
        console.log('[AutoDisbursementModal] Already processing, ignoring click');
        return;
    }
    
    if (!selectedBankAccountId.value) {
        error.value = 'Please select a bank account';
        return;
    }
    
    console.log('[AutoDisbursementModal] Disburse to Bank clicked', {
        paymentRequestId: props.paymentRequestId,
        voucherCode: props.voucherCode,
        amount: props.amount,
        bankAccountId: selectedBankAccountId.value,
        rememberChoice: rememberChoice.value,
    });

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
        
        console.log('[AutoDisbursementModal] Sending disburse request', payload);
        const { data } = await axios.post('/api/v1/vouchers/confirm-payment', payload);

        console.log('[AutoDisbursementModal] Disburse to Bank response', data);
        
        if (data.success) {
            const disbursement = data.data?.disbursement;
            console.log('[AutoDisbursementModal] Disbursement result', disbursement);
            
            if (disbursement?.success) {
                console.log('[AutoDisbursementModal] Disbursement success - closing modal');
                // Success - close modal and notify
                emit('update:open', false);
                emit('confirmed');
            } else {
                console.log('[AutoDisbursementModal] Disbursement failed', disbursement?.message);
                // Disbursement failed, but payment was confirmed
                error.value = disbursement?.message || 'Disbursement failed. Payment was confirmed but funds remain in wallet.';
            }
        } else {
            console.log('[AutoDisbursementModal] API call failed', data.message);
            error.value = data.message || 'Failed to process disbursement';
        }
    } catch (err: any) {
        console.error('[AutoDisbursementModal] Disbursement error:', err);
        error.value = err.response?.data?.message || err.message || 'Failed to process disbursement';
    } finally {
        disbursing.value = false;
        processing.value = false;
    }
};

const getBankName = (code: string): string => {
    // Simple bank name mapping - could be enhanced
    const names: Record<string, string> = {
        'GXCHPHM2XXX': 'GCash',
        'PYMYPHM2XXX': 'PayMaya',
        'MBTCPHM2XXX': 'Metrobank',
        'BOPIPHMM': 'BPI',
        'BNORPHM2XXX': 'BDO',
    };
    return names[code] || code;
};

onMounted(() => {
    if (props.open) {
        loadBankAccounts();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="(val) => $emit('update:open', val)">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle>Confirm Payment</DialogTitle>
                <DialogDescription>
                    Choose where you want to receive the funds after confirmation.
                </DialogDescription>
            </DialogHeader>

            <!-- Amount Display -->
            <div class="p-4 bg-muted rounded-lg text-center">
                <p class="text-sm text-muted-foreground mb-1">Amount</p>
                <p class="text-3xl font-bold">
                    ₱{{ amount.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                </p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-8">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>

            <!-- No Bank Accounts -->
            <Alert v-else-if="!hasAccounts">
                <Info class="h-4 w-4" />
                <AlertDescription>
                    You don't have any saved bank accounts yet. Funds will be transferred to your wallet.
                    <a href="/settings/profile" class="underline">Add a bank account</a> to enable auto-disbursement.
                </AlertDescription>
            </Alert>

            <!-- Bank Account Selection -->
            <div v-else class="space-y-4">
                <div class="grid gap-2">
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
                <div class="flex items-center justify-between">
                    <div class="space-y-0.5">
                        <Label>Remember my choice</Label>
                        <p class="text-xs text-muted-foreground">
                            Auto-disburse future payments to this account
                        </p>
                    </div>
                    <Switch v-model:checked="rememberChoice" />
                </div>

                <!-- Selected Account Info -->
                <Alert v-if="selectedAccount">
                    <Building2 class="h-4 w-4" />
                    <AlertDescription>
                        Funds will be sent to <strong>{{ selectedAccount.label || getBankName(selectedAccount.bank_code) }}</strong>
                        ({{ selectedAccount.account_number }})
                    </AlertDescription>
                </Alert>
            </div>

            <!-- Error Display -->
            <Alert v-if="error" variant="destructive">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <DialogFooter class="flex gap-2">
                <!-- Transfer to Wallet Button -->
                <Button
                    type="button"
                    variant="outline"
                    @click="handleTransferToWallet"
                    :disabled="processing || disbursing"
                    class="flex-1"
                >
                    <Loader2 v-if="disbursing" class="h-4 w-4 mr-2 animate-spin" />
                    <Wallet v-else class="h-4 w-4 mr-2" />
                    {{ disbursing ? 'Confirming...' : 'Transfer to Wallet' }}
                </Button>

                <!-- Disburse to Bank Button -->
                <Button
                    type="button"
                    @click="handleDisburseToBank"
                    :disabled="!hasAccounts || !selectedBankAccountId || processing || disbursing"
                    class="flex-1"
                >
                    <Loader2 v-if="disbursing" class="h-4 w-4 mr-2 animate-spin" />
                    <Building2 v-else class="h-4 w-4 mr-2" />
                    {{ disbursing ? 'Disbursing...' : 'Disburse to Bank' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
