<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useTransactionApi } from '@/composables/useTransactionApi';
import { useDepositApi } from '@/composables/useDepositApi';
import { useDebounce } from '@/composables/useDebounce';
import type { TransactionData, TransactionStats, TransactionListResponse } from '@/composables/useTransactionApi';
import type { DepositTransactionData, DepositStats, DepositListResponse } from '@/composables/useDepositApi';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Search, Download, Receipt, DollarSign, Calendar, TrendingUp, Loader2, ArrowDownLeft, ArrowUpRight, Users } from 'lucide-vue-next';
import TransactionDetailModal from '@/components/TransactionDetailModal.vue';
import SenderDetailModal from '@/components/SenderDetailModal.vue';
import GatewayBadge from '@/components/GatewayBadge.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transactions', href: '#' },
];

// Tab state
const activeTab = ref<'disbursements' | 'deposits'>('disbursements');

// Disbursements (existing)
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
const filterBank = ref('');
const filterRail = ref('');
const filterStatus = ref('');

// Column visibility
const showRailColumn = ref(true);

const selectedTransaction = ref<TransactionData | null>(null);
const isDetailModalOpen = ref(false);

const openTransactionDetail = (transaction: TransactionData) => {
    selectedTransaction.value = transaction;
    isDetailModalOpen.value = true;
};

// Sender detail modal
const selectedSenderId = ref<number | null>(null);
const isSenderModalOpen = ref(false);

const openSenderDetail = (senderId: number) => {
    selectedSenderId.value = senderId;
    isSenderModalOpen.value = true;
};

const fetchTransactions = async (page: number = 1) => {
    try {
        const response = await listTransactions({
            search: debouncedSearchQuery.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            bank: filterBank.value || undefined,
            rail: filterRail.value || undefined,
            status: filterStatus.value || undefined,
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
            bank: filterBank.value || undefined,
            rail: filterRail.value || undefined,
            status: filterStatus.value || undefined,
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
    filterBank.value = '';
    filterRail.value = '';
    filterStatus.value = '';
    await applyFilters();
};

const exportTransactions = () => {
    exportAPI({
        search: debouncedSearchQuery.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        bank: filterBank.value || undefined,
        rail: filterRail.value || undefined,
        status: filterStatus.value || undefined,
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

// Helper to get recipient identifier
const getRecipientIdentifier = (disbursement: any) => {
    return disbursement.recipient_identifier || 'N/A';
};

// Helper to get bank/recipient name
const getBankName = (disbursement: any) => {
    return disbursement.recipient_name || 'N/A';
};

// Helper to get rail
const getRail = (disbursement: any) => {
    return disbursement.metadata?.rail;
};

// Helper to get transaction ID
const getTransactionId = (disbursement: any) => {
    return disbursement.transaction_id;
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

// Auto-filter when date range or filters change
watch([dateFrom, dateTo, filterBank, filterRail, filterStatus], () => {
    applyFilters();
});

// Deposits (new)
const { loading: depositsLoading, listDeposits, getDepositStats } = useDepositApi();
const deposits = ref<DepositListResponse['data']>([]);
const depositsPagination = ref({
    current_page: 1,
    per_page: 20,
    total: 0,
    last_page: 1,
});
const depositStats = ref<DepositStats>({
    total: 0,
    total_amount: 0,
    today: 0,
    this_month: 0,
    unique_senders: 0,
    currency: 'PHP',
});

const depositSearchQuery = ref('');
const debouncedDepositSearchQuery = useDebounce(depositSearchQuery, 500);
const depositDateFrom = ref('');
const depositDateTo = ref('');
const depositInstitution = ref('');

const fetchDeposits = async (page: number = 1) => {
    try {
        const response = await listDeposits({
            search: debouncedDepositSearchQuery.value || undefined,
            date_from: depositDateFrom.value || undefined,
            date_to: depositDateTo.value || undefined,
            institution: depositInstitution.value || undefined,
            per_page: depositsPagination.value.per_page,
            page,
        });
        
        deposits.value = response.data;
        depositsPagination.value = response.pagination;
    } catch (error) {
        console.error('Failed to fetch deposits:', error);
    }
};

const fetchDepositStats = async () => {
    try {
        const response = await getDepositStats({
            date_from: depositDateFrom.value || undefined,
            date_to: depositDateTo.value || undefined,
            institution: depositInstitution.value || undefined,
        });
        
        depositStats.value = response;
    } catch (error) {
        console.error('Failed to fetch deposit stats:', error);
    }
};

const applyDepositFilters = async () => {
    await Promise.all([
        fetchDeposits(1),
        fetchDepositStats(),
    ]);
};

const clearDepositFilters = async () => {
    depositSearchQuery.value = '';
    depositDateFrom.value = '';
    depositDateTo.value = '';
    depositInstitution.value = '';
    await applyDepositFilters();
};

// Deposits pagination
const depositsPaginationLinks = computed(() => {
    const links = [];
    
    links.push({
        label: '&laquo; Previous',
        page: depositsPagination.value.current_page - 1,
        active: false,
        disabled: depositsPagination.value.current_page === 1,
    });
    
    for (let i = 1; i <= depositsPagination.value.last_page; i++) {
        links.push({
            label: i.toString(),
            page: i,
            active: i === depositsPagination.value.current_page,
            disabled: false,
        });
    }
    
    links.push({
        label: 'Next &raquo;',
        page: depositsPagination.value.current_page + 1,
        active: false,
        disabled: depositsPagination.value.current_page === depositsPagination.value.last_page,
    });
    
    return links;
});

const goToDepositPage = async (page: number) => {
    if (page >= 1 && page <= depositsPagination.value.last_page) {
        await fetchDeposits(page);
    }
};

// Auto-search when debounced deposit query changes
watch(debouncedDepositSearchQuery, () => {
    fetchDeposits(1);
});

// Auto-filter when deposit filters change
watch([depositDateFrom, depositDateTo, depositInstitution], () => {
    applyDepositFilters();
});

// Tab switching
watch(activeTab, async (newTab) => {
    if (newTab === 'deposits' && deposits.value.length === 0) {
        await Promise.all([
            fetchDeposits(),
            fetchDepositStats(),
        ]);
    }
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
                description="View disbursements and incoming deposits"
            />

            <!-- Tabs -->
            <div class="flex gap-2 border-b">
                <button
                    @click="activeTab = 'disbursements'"
                    :class="[
                        'flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors border-b-2',
                        activeTab === 'disbursements'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                >
                    <ArrowUpRight class="h-4 w-4" />
                    Disbursements
                </button>
                <button
                    @click="activeTab = 'deposits'"
                    :class="[
                        'flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors border-b-2',
                        activeTab === 'deposits'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                >
                    <ArrowDownLeft class="h-4 w-4" />
                    Deposits
                </button>
            </div>

            <!-- Disbursements View -->
            <div v-if="activeTab === 'disbursements'">
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
                    <div class="space-y-4 pt-4">
                        <div class="grid gap-4 sm:grid-cols-4">
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
                        <div class="grid gap-4 sm:grid-cols-3">
                            <select
                                v-model="filterBank"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">All Banks</option>
                                <option value="GXCHPHM2XXX">GCash</option>
                                <option value="PYMYPHM2XXX">PayMaya</option>
                                <option value="MBTCPHM2XXX">Metrobank</option>
                                <option value="BPIAPHM2XXX">BPI</option>
                                <option value="BNORPHM2XXX">BDO</option>
                            </select>
                            <select
                                v-model="filterRail"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">All Rails</option>
                                <option value="INSTAPAY">INSTAPAY</option>
                                <option value="PESONET">PESONET</option>
                            </select>
                            <select
                                v-model="filterStatus"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">All Status</option>
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                                <option value="Failed">Failed</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        <Button @click="clearFilters" variant="outline" size="sm" :disabled="loading">
                            Clear
                        </Button>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-muted-foreground flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="showRailColumn"
                                    type="checkbox"
                                    class="rounded border-input"
                                />
                                Show Rail Column
                            </label>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Table -->
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left">Voucher Code</th>
                                    <th class="px-4 py-3 text-left">Gateway</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-left">Recipient / Account</th>
                                    <th v-if="showRailColumn" class="px-4 py-3 text-left">Rail</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Transaction ID</th>
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
                                        class="border-b hover:bg-muted/50 cursor-pointer transition-colors"
                                        @click="openTransactionDetail(transaction)"
                                    >
                                        <td class="px-4 py-3 font-mono font-semibold">
                                            {{ transaction.code }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <GatewayBadge
                                                v-if="transaction.disbursement"
                                                :gateway="transaction.disbursement.gateway"
                                                size="sm"
                                            />
                                            <span v-else class="text-xs text-muted-foreground">N/A</span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-green-600">
                                            {{ formatAmount(transaction.amount, transaction.currency) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div v-if="transaction.disbursement" class="flex items-center gap-2">
                                                <div>
                                                    <div class="font-medium text-sm">
                                                        {{ getBankName(transaction.disbursement) }}
                                                    </div>
                                                    <div class="text-xs text-muted-foreground font-mono">
                                                        {{ getMaskedAccount(getRecipientIdentifier(transaction.disbursement)) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <span v-else class="text-xs text-muted-foreground">N/A</span>
                                        </td>
                                        <td v-if="showRailColumn" class="px-4 py-3">
                                            <Badge v-if="transaction.disbursement && getRail(transaction.disbursement)" :variant="getRailVariant(getRail(transaction.disbursement))" class="text-xs">
                                                {{ getRail(transaction.disbursement) }}
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
                                                {{ getTransactionId(transaction.disbursement) }}
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

            <!-- Transaction Detail Modal -->
            <TransactionDetailModal
                :transaction="selectedTransaction"
                :open="isDetailModalOpen"
                @update:open="isDetailModalOpen = $event"
            />
            </div>
            <!-- End Disbursements View -->

            <!-- Deposits View -->
            <div v-if="activeTab === 'deposits'">
            <!-- Deposit Stats Cards -->
            <div v-if="depositsLoading" class="flex justify-center py-8">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
            <div v-else class="grid gap-4 md:grid-cols-5">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Deposits</CardTitle>
                        <Receipt class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ depositStats.total }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Received</CardTitle>
                        <DollarSign class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatAmount(depositStats.total_amount, depositStats.currency) }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Unique Senders</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ depositStats.unique_senders }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Today</CardTitle>
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ depositStats.today }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">This Month</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ depositStats.this_month }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Deposits Table Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Deposit History</CardTitle>
                            <CardDescription>{{ depositsPagination.total }} deposits found</CardDescription>
                        </div>
                    </div>
                    
                    <!-- Deposit Filters -->
                    <div class="space-y-4 pt-4">
                        <div class="grid gap-4 sm:grid-cols-4">
                            <div class="relative sm:col-span-2">
                                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    v-model="depositSearchQuery"
                                    placeholder="Search by sender... (auto-search enabled)"
                                    class="pl-8"
                                />
                            </div>
                            <Input
                                v-model="depositDateFrom"
                                type="date"
                                placeholder="From date"
                            />
                            <Input
                                v-model="depositDateTo"
                                type="date"
                                placeholder="To date"
                            />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <select
                                v-model="depositInstitution"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">All Institutions</option>
                                <option value="GXCHPHM2XXX">GCash</option>
                                <option value="PMYAPHM2XXX">Maya</option>
                                <option value="MBTCPHM2XXX">Metrobank</option>
                                <option value="BOPIPHM2XXX">BPI</option>
                                <option value="BDONPHM2XXX">BDO</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        <Button @click="clearDepositFilters" variant="outline" size="sm" :disabled="depositsLoading">
                            Clear
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Deposits Table -->
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left">Sender</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-left">Payment Method</th>
                                    <th class="px-4 py-3 text-left">Operation ID</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="depositsLoading">
                                    <td colspan="5" class="px-4 py-8 text-center">
                                        <Loader2 class="inline h-6 w-6 animate-spin text-muted-foreground" />
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="deposit in deposits"
                                        :key="deposit.operation_id"
                                        class="border-b hover:bg-muted/50 cursor-pointer transition-colors"
                                        @click="openSenderDetail(deposit.sender_id)"
                                    >
                                        <td class="px-4 py-3">
                                            <div>
                                                <div class="font-medium text-sm">{{ deposit.sender_name }}</div>
                                                <div class="text-xs text-muted-foreground font-mono">{{ deposit.sender_mobile }}</div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-green-600">
                                            {{ formatAmount(deposit.amount, deposit.currency) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge variant="outline" class="text-xs">
                                                {{ deposit.institution_name }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="font-mono text-xs text-muted-foreground">{{ deposit.operation_id || 'N/A' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">
                                            {{ deposit.timestamp ? formatDate(deposit.timestamp) : 'N/A' }}
                                        </td>
                                    </tr>
                                    <tr v-if="deposits.length === 0">
                                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                            No deposits found
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Deposits Pagination -->
                    <div v-if="depositsPagination.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (depositsPagination.current_page - 1) * depositsPagination.per_page + 1 }} to
                            {{ Math.min(depositsPagination.current_page * depositsPagination.per_page, depositsPagination.total) }}
                            of {{ depositsPagination.total }} results
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-for="link in depositsPaginationLinks"
                                :key="link.label"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="link.disabled || depositsLoading"
                                @click="goToDepositPage(link.page)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>
            <!-- End Deposits View -->

            <!-- Sender Detail Modal -->
            <SenderDetailModal
                :sender-id="selectedSenderId"
                :open="isSenderModalOpen"
                @update:open="isSenderModalOpen = $event"
            />
        </div>
    </AppLayout>
</template>
