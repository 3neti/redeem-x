<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from '@/lib/axios';
import { BANKS } from '@/data/banks';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { useToast } from '@/components/ui/toast/use-toast';
import { Plus, Trash2, Star, Edit2, Loader2, Building2 } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import BankSelect from '@/components/BankSelect.vue';

interface BankAccount {
    id: string;
    bank_code: string;
    account_number: string;
    label: string | null;
    is_default: boolean;
    created_at: string;
}

interface Bank {
    code: string;
    name: string;
}

const { toast } = useToast();

// State
const loading = ref(true);
const saving = ref(false);
const bankAccounts = ref<BankAccount[]>([]);
const banks = ref<Bank[]>([]);

// Dialog state
const showDialog = ref(false);
const dialogMode = ref<'create' | 'edit'>('create');
const editingAccountId = ref<string | null>(null);

// Form state
const form = ref({
    bank_code: '',
    account_number: '',
    label: '',
    is_default: false,
});

const errors = ref<Record<string, string>>({});

// Load bank accounts
const loadBankAccounts = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/v1/user/bank-accounts');
        if (data.data?.bank_accounts) {
            bankAccounts.value = data.data.bank_accounts;
        }
    } catch (error: any) {
        console.error('Failed to load bank accounts:', error);
        toast({
            title: 'Error',
            description: 'Failed to load bank accounts',
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

// Load available banks from shared data source
// Uses same source as form-flow (BankEMISelect component)
// which reads from resources/documents/banks.json
const loadBanks = () => {
    // BANKS is already parsed from banks.json by @/data/banks
    // This ensures consistency across redemption flow, form-flow, and profile settings
    banks.value = BANKS.map(bank => ({
        code: bank.code,
        name: bank.name,
    }));
};

// Open create dialog
const openCreateDialog = () => {
    dialogMode.value = 'create';
    editingAccountId.value = null;
    form.value = {
        bank_code: '',
        account_number: '',
        label: '',
        is_default: bankAccounts.value.length === 0, // Auto-default if first account
    };
    errors.value = {};
    showDialog.value = true;
};

// Open edit dialog
const openEditDialog = (account: BankAccount) => {
    dialogMode.value = 'edit';
    editingAccountId.value = account.id;
    form.value = {
        bank_code: account.bank_code,
        account_number: account.account_number,
        label: account.label || '',
        is_default: account.is_default,
    };
    errors.value = {};
    showDialog.value = true;
};

// Save account (create or update)
const saveAccount = async () => {
    saving.value = true;
    errors.value = {};

    try {
        const payload = {
            bank_code: form.value.bank_code,
            account_number: form.value.account_number,
            label: form.value.label || null,
            is_default: form.value.is_default,
        };

        if (dialogMode.value === 'create') {
            await axios.post('/api/v1/user/bank-accounts', payload);
            toast({
                title: 'Account Added',
                description: 'Bank account added successfully',
            });
        } else {
            await axios.put(`/api/v1/user/bank-accounts/${editingAccountId.value}`, payload);
            toast({
                title: 'Account Updated',
                description: 'Bank account updated successfully',
            });
        }

        showDialog.value = false;
        await loadBankAccounts();
    } catch (error: any) {
        console.error('Failed to save bank account:', error);
        
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else {
            toast({
                title: 'Error',
                description: error.response?.data?.message || 'Failed to save bank account',
                variant: 'destructive',
            });
        }
    } finally {
        saving.value = false;
    }
};

// Delete account
const deleteAccount = async (account: BankAccount) => {
    if (!confirm(`Are you sure you want to delete "${account.label || account.account_number}"?`)) {
        return;
    }

    try {
        await axios.delete(`/api/v1/user/bank-accounts/${account.id}`);
        toast({
            title: 'Account Deleted',
            description: 'Bank account deleted successfully',
        });
        await loadBankAccounts();
    } catch (error: any) {
        console.error('Failed to delete bank account:', error);
        toast({
            title: 'Error',
            description: error.response?.data?.message || 'Failed to delete bank account',
            variant: 'destructive',
        });
    }
};

// Set as default
const setAsDefault = async (account: BankAccount) => {
    try {
        await axios.put(`/api/v1/user/bank-accounts/${account.id}/set-default`);
        toast({
            title: 'Default Updated',
            description: 'Default bank account updated successfully',
        });
        await loadBankAccounts();
    } catch (error: any) {
        console.error('Failed to set default account:', error);
        toast({
            title: 'Error',
            description: error.response?.data?.message || 'Failed to set default account',
            variant: 'destructive',
        });
    }
};

// Get bank name from code
const getBankName = (code: string): string => {
    const bank = banks.value.find(b => b.code === code);
    return bank?.name || code;
};

// Computed
const hasAccounts = computed(() => bankAccounts.value.length > 0);

onMounted(() => {
    loadBanks();
    loadBankAccounts();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium">Bank Accounts</h3>
                <p class="text-sm text-muted-foreground">
                    Manage bank accounts for automatic disbursement
                </p>
            </div>
            <Button @click="openCreateDialog" size="sm">
                <Plus class="h-4 w-4 mr-2" />
                Add Account
            </Button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
        </div>

        <!-- Empty State -->
        <div
            v-else-if="!hasAccounts"
            class="rounded-lg border border-dashed p-12 text-center"
        >
            <Building2 class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
            <h3 class="text-lg font-semibold mb-2">No bank accounts yet</h3>
            <p class="text-sm text-muted-foreground mb-4">
                Add a bank account to enable automatic disbursement for settlement vouchers
            </p>
            <Button @click="openCreateDialog" size="sm">
                <Plus class="h-4 w-4 mr-2" />
                Add Your First Account
            </Button>
        </div>

        <!-- Accounts List -->
        <div v-else class="space-y-3">
            <div
                v-for="account in bankAccounts"
                :key="account.id"
                class="rounded-lg border p-4 hover:bg-muted/50 transition-colors"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-medium">
                                {{ account.label || getBankName(account.bank_code) }}
                            </h4>
                            <Star
                                v-if="account.is_default"
                                class="h-4 w-4 fill-yellow-400 text-yellow-400"
                            />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ getBankName(account.bank_code) }}
                        </p>
                        <p class="text-sm font-mono">{{ account.account_number }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="!account.is_default"
                            @click="setAsDefault(account)"
                            variant="ghost"
                            size="sm"
                            title="Set as default"
                        >
                            <Star class="h-4 w-4" />
                        </Button>
                        <Button
                            @click="openEditDialog(account)"
                            variant="ghost"
                            size="sm"
                            title="Edit"
                        >
                            <Edit2 class="h-4 w-4" />
                        </Button>
                        <Button
                            @click="deleteAccount(account)"
                            variant="ghost"
                            size="sm"
                            title="Delete"
                        >
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Dialog -->
        <Dialog v-model:open="showDialog">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>
                        {{ dialogMode === 'create' ? 'Add Bank Account' : 'Edit Bank Account' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            dialogMode === 'create'
                                ? 'Add a new bank account for automatic disbursement'
                                : 'Update bank account details'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="saveAccount" class="space-y-4">
                    <!-- Bank Selection -->
                    <div class="grid gap-2">
                        <Label for="bank_code">Bank *</Label>
                        <BankSelect
                            v-model="form.bank_code"
                            :banks="banks"
                            placeholder="Select bank"
                            :disabled="dialogMode === 'edit'"
                        />
                        <InputError :message="errors.bank_code?.[0]" />
                    </div>

                    <!-- Account Number -->
                    <div class="grid gap-2">
                        <Label for="account_number">Account Number *</Label>
                        <Input
                            id="account_number"
                            v-model="form.account_number"
                            placeholder="Enter account number or mobile"
                            required
                            :disabled="dialogMode === 'edit'"
                        />
                        <p class="text-xs text-muted-foreground">
                            For GCash/PayMaya, use mobile number (09XXXXXXXXX)
                        </p>
                        <InputError :message="errors.account_number?.[0]" />
                    </div>

                    <!-- Label -->
                    <div class="grid gap-2">
                        <Label for="label">Label</Label>
                        <Input
                            id="label"
                            v-model="form.label"
                            placeholder="e.g., Primary GCash, BPI Savings"
                        />
                        <InputError :message="errors.label?.[0]" />
                    </div>

                    <!-- Default Toggle -->
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <Label>Set as default</Label>
                            <p class="text-xs text-muted-foreground">
                                Use this account for automatic disbursement
                            </p>
                        </div>
                        <Switch v-model:checked="form.is_default" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showDialog = false"
                            :disabled="saving"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="saving">
                            <Loader2 v-if="saving" class="h-4 w-4 mr-2 animate-spin" />
                            {{ dialogMode === 'create' ? 'Add Account' : 'Save Changes' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
