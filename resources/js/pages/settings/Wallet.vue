<script setup lang="ts">
import WalletController from '@/actions/App/Http/Controllers/Settings/WalletController';
import { edit } from '@/routes/wallet';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useToast } from '@/components/ui/toast';
import { useWalletBalance } from '@/composables/useWalletBalance';
import type { User } from '@/types';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Wallet as WalletIcon, Plus, ArrowUp, ArrowDown } from 'lucide-vue-next';

interface Transaction {
    id: number;
    type: string;
    amount: number;
    confirmed: boolean;
    created_at: string;
}

interface Props {
    wallet: {
        balance: number;
        currency: string;
    };
    transactions: Transaction[];
    status?: string;
}

const props = defineProps<Props>();

// Set up wallet balance composable with Echo listener
const { balance: balanceFloat, currency: currencyCode, realtimeNote, formattedBalance } = useWalletBalance();
const balance = ref<number>(props.wallet.balance);
const currency = ref<string>(props.wallet.currency);

const page = usePage();
const { toast } = useToast();

const formatAmount = (value: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(value);
};

// Watch balance changes from useWalletBalance and show toast
let previousBalance = balance.value;
watch(balanceFloat, (newBalance, oldBalance) => {
    if (oldBalance === null || oldBalance === undefined) return;
    
    const diff = newBalance - oldBalance;
    if (diff === 0) return; // Skip if no change
    
    const diffFormatted = formatAmount(Math.abs(diff), currency.value);
    
    console.log('[Wallet Settings] Balance changed:', {
        oldBalance,
        newBalance,
        diff,
        diffFormatted,
        isDeposit: diff > 0,
    });
    
    toast({
        title: diff > 0 ? '💰 Deposit Received' : '💸 Payment Sent',
        description: `${diff > 0 ? '+' : ''}${diffFormatted} • New balance: ${formatAmount(newBalance, currency.value)}`,
        duration: 5000,
    });
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Wallet settings',
        href: edit().url,
    },
];

const amount = ref(100);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getTransactionIcon = (type: string) => {
    return type === 'deposit' ? ArrowDown : ArrowUp;
};

const getTransactionColor = (type: string) => {
    return type === 'deposit' ? 'text-green-600' : 'text-red-600';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Wallet settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <!-- Current Balance -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <div>
                            <CardTitle>Current Balance</CardTitle>
                            <CardDescription>Your available wallet balance</CardDescription>
                        </div>
                        <WalletIcon class="h-8 w-8 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">
                            {{ formattedBalance }}
                        </div>
                        <p v-if="realtimeNote" class="text-sm text-muted-foreground mt-2">
                            {{ realtimeNote }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Add Funds -->
                <HeadingSmall
                    title="Add Funds"
                    description="Deposit money into your wallet"
                />

                <Form
                    v-bind="WalletController.store.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="amount">Amount ({{ wallet.currency }})</Label>
                        <Input
                            id="amount"
                            type="number"
                            class="mt-1 block w-full"
                            name="amount"
                            v-model="amount"
                            required
                            min="1"
                            max="100000"
                            step="0.01"
                            placeholder="100.00"
                        />
                        <InputError class="mt-2" :message="errors.amount" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            type="submit"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            Add Funds
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful || status"
                                class="text-sm text-green-600"
                            >
                                {{ status || 'Saved.' }}
                            </p>
                        </Transition>
                    </div>
                </Form>

                <!-- Transaction History -->
                <div class="space-y-4">
                    <HeadingSmall
                        title="Recent Transactions"
                        description="Your last 10 wallet transactions"
                    />

                    <Card>
                        <CardContent class="p-0">
                            <div v-if="transactions.length === 0" class="p-8 text-center text-muted-foreground">
                                No transactions yet
                            </div>
                            <div v-else class="divide-y">
                                <div
                                    v-for="transaction in transactions"
                                    :key="transaction.id"
                                    class="flex items-center justify-between p-4"
                                >
                                    <div class="flex items-center gap-4">
                                        <component
                                            :is="getTransactionIcon(transaction.type)"
                                            :class="['h-5 w-5', getTransactionColor(transaction.type)]"
                                        />
                                        <div>
                                            <div class="font-medium capitalize">{{ transaction.type }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                {{ formatDate(transaction.created_at) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div :class="['font-semibold', getTransactionColor(transaction.type)]">
                                        {{ transaction.type === 'deposit' ? '+' : '-' }}{{ formatAmount(Math.abs(transaction.amount), wallet.currency) }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
