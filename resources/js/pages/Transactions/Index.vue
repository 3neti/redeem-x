<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useTransactionApi } from '@/composables/useTransactionApi';
import { useDebounce } from '@/composables/useDebounce';
import type { TransactionData, TransactionStats, TransactionListResponse } from '@/composables/useTransactionApi';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Search, Download, Receipt, DollarSign, Calendar, TrendingUp, Loader2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transactions', href: '#' },
];

const { loading, listTransactions, getStats, exportTransactions: exportAPI } = useTransactionApi();

const transactions = ref<TransactionListResponse['data']>([]);
const pagination = ref({
    current_page: 1,
    per_page: 20,
    total: 0,
    last_page: 1,
});
const stats = ref<TransactionStats>({
    total: 0,
    total_amount: 0,
    today: 0,
    this_month: 0,
    currency: 'PHP',
});

const searchQuery = ref('');
const debouncedSearchQuery = useDebounce(searchQuery, 500);
const dateFrom = ref('');
const dateTo = ref('');

const fetchTransactions = async (page: number = 1) => {
    try {
        const response = await listTransactions({
            search: debouncedSearchQuery.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            per_page: pagination.value.per_page,
            page,
        });
        
        transactions.value = response.data;
        pagination.value = response.pagination;
    } catch (error) {
        console.error('Failed to fetch transactions:', error);
    }
};

const fetchStats = async () => {
    try {
        const response = await getStats({
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        });
        
        stats.value = response;
    } catch (error) {
        console.error('Failed to fetch stats:', error);
    }
};

const applyFilters = async () => {
    await Promise.all([
        fetchTransactions(1),
        fetchStats(),
    ]);
};

const clearFilters = async () => {
    searchQuery.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    await applyFilters();
};

const exportTransactions = () => {
    exportAPI({
        search: debouncedSearchQuery.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    });
};

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getRailVariant = (rail?: string) => {
    switch (rail) {
        case 'INSTAPAY':
            return 'default';
        case 'PESONET':
            return 'secondary';
        default:
            return 'outline';
    }
};

const getStatusVariant = (status?: string) => {
    switch (status?.toLowerCase()) {
        case 'pending':
            return 'secondary';
        case 'completed':
        case 'success':
            return 'default';
        case 'failed':
        case 'error':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getMaskedAccount = (account?: string) => {
    if (!account || account.length <= 4) return account || 'N/A';
    return '***' + account.slice(-4);
};

// Pagination helpers
const paginationLinks = computed(() => {
    const links = [];
    
    // Previous
    links.push({
        label: '&laquo; Previous',
        page: pagination.value.current_page - 1,
        active: false,
        disabled: pagination.value.current_page === 1,
    });
    
    // Pages
    for (let i = 1; i <= pagination.value.last_page; i++) {
        links.push({
            label: i.toString(),
            page: i,
            active: i === pagination.value.current_page,
            disabled: false,
        });
    }
    
    // Next
    links.push({
        label: 'Next &raquo;',
        page: pagination.value.current_page + 1,
        active: false,
        disabled: pagination.value.current_page === pagination.value.last_page,
    });
    
    return links;
});

const goToPage = async (page: number) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        await fetchTransactions(page);
    }
};

// Auto-search when debounced query changes
watch(debouncedSearchQuery, () => {
    fetchTransactions(1);
});

// Auto-filter when date range changes
watch([dateFrom, dateTo], () => {
    applyFilters();
});

onMounted(async () => {
    await Promise.all([
        fetchTransactions(),
        fetchStats(),
    ]);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                title="Transaction History"
                description="View and export all voucher redemptions"
            />

            <!-- Stats Cards -->
            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
            <div v-else class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Transactions</CardTitle>
                        <Receipt class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Amount</CardTitle>
                        <DollarSign class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatAmount(stats.total_amount, stats.currency) }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Today</CardTitle>
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.today }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">This Month</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.this_month }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters and Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Redemption History</CardTitle>
                            <CardDescription>{{ pagination.total }} transactions found</CardDescription>
                        </div>
                        <Button @click="exportTransactions" variant="outline" :disabled="loading">
                            <Download class="mr-2 h-4 w-4" />
                            Export CSV
                        </Button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="grid gap-4 pt-4 sm:grid-cols-4">
                        <div class="relative sm:col-span-2">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by code... (auto-search enabled)"
                                class="pl-8"
                            />
                        </div>
                        <Input
                            v-model="dateFrom"
                            type="date"
                            placeholder="From date"
                        />
                        <Input
                            v-model="dateTo"
                            type="date"
                            placeholder="To date"
                        />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button @click="clearFilters" variant="outline" size="sm" :disabled="loading">
                            Clear
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Table -->
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left">Voucher Code</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-left">Bank / Account</th>
                                    <th class="px-4 py-3 text-left">Rail</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Operation ID</th>
                                    <th class="px-4 py-3 text-left">Redeemed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading">
                                    <td colspan="4" class="px-4 py-8 text-center">
                                        <Loader2 class="inline h-6 w-6 animate-spin text-muted-foreground" />
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="transaction in transactions"
                                        :key="transaction.code"
                                        class="border-b hover:bg-muted/50 cursor-pointer"
                                    >
                                        <td class="px-4 py-3 font-mono font-semibold">
                                            {{ transaction.code }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-green-600">
                                            {{ formatAmount(transaction.amount, transaction.currency) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div v-if="transaction.disbursement" class="flex items-center gap-2">
                                                <div>
                                                    <div class="font-medium text-sm">
                                                        {{ transaction.disbursement.bank_name || 'N/A' }}
                                                    </div>
                                                    <div class="text-xs text-muted-foreground font-mono">
                                                        {{ getMaskedAccount(transaction.disbursement.account) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <span v-else class="text-xs text-muted-foreground">N/A</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge v-if="transaction.disbursement" :variant="getRailVariant(transaction.disbursement.rail)" class="text-xs">
                                                {{ transaction.disbursement.rail }}
                                            </Badge>
                                            <span v-else class="text-xs text-muted-foreground">N/A</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge v-if="transaction.disbursement" :variant="getStatusVariant(transaction.disbursement.status)" class="text-xs">
                                                {{ transaction.disbursement.status }}
                                            </Badge>
                                            <span v-else class="text-xs text-muted-foreground">N/A</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span v-if="transaction.disbursement" class="font-mono text-xs text-muted-foreground">
                                                {{ transaction.disbursement.operation_id }}
                                            </span>
                                            <span v-else class="text-xs text-muted-foreground">N/A</span>
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">
                                            {{ formatDate(transaction.redeemed_at) }}
                                        </td>
                                    </tr>
                                    <tr v-if="transactions.length === 0">
                                        <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                                            No transactions found
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }} to
                            {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
                            of {{ pagination.total }} results
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-for="link in paginationLinks"
                                :key="link.label"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="link.disabled || loading"
                                @click="goToPage(link.page)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
