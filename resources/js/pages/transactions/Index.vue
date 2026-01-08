<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useTransactionApi } from '@/composables/useTransactionApi';
import { useDepositApi } from '@/composables/useDepositApi';
import { useWalletTransactionApi } from '@/composables/useWalletTransactionApi';
import { useDebounce } from '@/composables/useDebounce';
import type { TransactionData, TransactionStats, TransactionListResponse } from '@/composables/useTransactionApi';
import type { DepositTransactionData, DepositStats, DepositListResponse } from '@/composables/useDepositApi';
import type { WalletTransactionData, WalletTransactionListResponse } from '@/composables/useWalletTransactionApi';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Search, Download, Receipt, DollarSign, Calendar, TrendingUp, Loader2, ArrowDownLeft, ArrowUpRight, Users, Wallet } from 'lucide-vue-next';
import TransactionDetailModal from '@/components/TransactionDetailModal.vue';
import SenderDetailModal from '@/components/SenderDetailModal.vue';
import WalletTransactionDetailModal from '@/components/WalletTransactionDetailModal.vue';
import GatewayBadge from '@/components/GatewayBadge.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transactions', href: '#' },
];

// Tab state
const activeTab = ref<'vouchers' | 'wallet'>('vouchers');

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

const selectedTransaction = ref<TransactionData | null>(null);
const isDetailModalOpen = ref(false);

const openTransactionDetail = (transaction: TransactionData) => {
    selectedTransaction.value = transaction;
    isDetailModalOpen.value = true;
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

// ===== DEPOSITS FUNCTIONALITY =====
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

// Sender modal state
const selectedSenderId = ref<number | null>(null);
const isSenderModalOpen = ref(false);

const openSenderDetail = (senderId: number) => {
    selectedSenderId.value = senderId;
    isSenderModalOpen.value = true;
};

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

watch(debouncedDepositSearchQuery, () => {
    fetchDeposits(1);
});

watch([depositDateFrom, depositDateTo, depositInstitution], () => {
    applyDepositFilters();
});

watch(activeTab, async (newTab) => {
    if (newTab === 'wallet' && walletTransactions.value.length === 0) {
        await fetchWalletTransactions();
    }
});

// ===== WALLET TRANSACTIONS FUNCTIONALITY =====
const { loading: walletLoading, listTransactions: listWalletTransactions } = useWalletTransactionApi();
const walletTransactions = ref<WalletTransactionData[]>([]);
const walletPagination = ref({
    current_page: 1,
    per_page: 20,
    total: 0,
    last_page: 1,
});

const walletSearchQuery = ref('');
const debouncedWalletSearchQuery = useDebounce(walletSearchQuery, 500);
const walletDateFrom = ref('');
const walletDateTo = ref('');
const walletTypeFilter = ref<'all' | 'deposit' | 'withdraw'>('all');

const selectedWalletTransaction = ref<WalletTransactionData | null>(null);
const isWalletDetailModalOpen = ref(false);

const openWalletTransactionDetail = (transaction: WalletTransactionData) => {
    selectedWalletTransaction.value = transaction;
    isWalletDetailModalOpen.value = true;
};

const fetchWalletTransactions = async (page: number = 1) => {
    try {
        const response = await listWalletTransactions({
            type: walletTypeFilter.value,
            search: debouncedWalletSearchQuery.value || undefined,
            date_from: walletDateFrom.value || undefined,
            date_to: walletDateTo.value || undefined,
            per_page: walletPagination.value.per_page,
            page,
        });
        
        walletTransactions.value = response.data;
        walletPagination.value = response.pagination;
    } catch (error) {
        console.error('Failed to fetch wallet transactions:', error);
    }
};

const clearWalletFilters = async () => {
    walletSearchQuery.value = '';
    walletDateFrom.value = '';
    walletDateTo.value = '';
    walletTypeFilter.value = 'all';
    await fetchWalletTransactions(1);
};

const walletPaginationLinks = computed(() => {
    const links = [];
    
    links.push({
        label: '&laquo; Previous',
        page: walletPagination.value.current_page - 1,
        active: false,
        disabled: walletPagination.value.current_page === 1,
    });
    
    for (let i = 1; i <= walletPagination.value.last_page; i++) {
        links.push({
            label: i.toString(),
            page: i,
            active: i === walletPagination.value.current_page,
            disabled: false,
        });
    }
    
    links.push({
        label: 'Next &raquo;',
        page: walletPagination.value.current_page + 1,
        active: false,
        disabled: walletPagination.value.current_page === walletPagination.value.last_page,
    });
    
    return links;
});

const goToWalletPage = async (page: number) => {
    if (page >= 1 && page <= walletPagination.value.last_page) {
        await fetchWalletTransactions(page);
    }
};

watch(debouncedWalletSearchQuery, () => {
    fetchWalletTransactions(1);
});

watch([walletDateFrom, walletDateTo, walletTypeFilter], () => {
    fetchWalletTransactions(1);
});

// Wallet transaction stats (computed from data)
const walletStats = computed(() => {
    const deposits = walletTransactions.value.filter(tx => tx.type === 'deposit');
    const withdrawals = walletTransactions.value.filter(tx => tx.type === 'withdraw');
    
    const totalDeposits = deposits.reduce((sum, tx) => sum + tx.amount, 0);
    const totalWithdrawals = withdrawals.reduce((sum, tx) => sum + tx.amount, 0);
    const netBalance = totalDeposits - totalWithdrawals;
    
    // Count today's transactions
    const today = new Date().toDateString();
    const todayCount = walletTransactions.value.filter(tx => 
        new Date(tx.created_at).toDateString() === today
    ).length;
    
    return {
        total: walletPagination.value.total,
        deposits: deposits.length,
        withdrawals: withdrawals.length,
        totalDeposits,
        totalWithdrawals,
        netBalance,
        today: todayCount,
        currency: 'PHP',
    };
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
                description="View voucher activity and wallet transactions"
            />

            <!-- Tabs -->
            <div class="flex gap-2 border-b">
                <button
                    @click="activeTab = 'vouchers'"
                    :class="[
                        'flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors border-b-2 whitespace-nowrap',
                        activeTab === 'vouchers'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                >
                    <Receipt class="h-4 w-4" />
                    Vouchers
                </button>
                <button
                    @click="activeTab = 'wallet'"
                    :class="[
                        'flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors border-b-2 whitespace-nowrap',
                        activeTab === 'wallet'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                >
                    <Wallet class="h-4 w-4" />
                    Wallet
                </button>
            </div>

            <!-- Vouchers View -->
            <div v-if="activeTab === 'vouchers'" class="space-y-6">
            <!-- Stats Cards -->
            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
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
                            <CardTitle>Voucher Activity</CardTitle>
                            <CardDescription>{{ pagination.total }} voucher transactions found</CardDescription>
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
                        <div></div>
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
                                    <th class="px-4 py-3 text-left">Rail</th>
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
                                            <Badge v-else variant="secondary" class="text-xs">
                                                Voucher Payment
                                            </Badge>
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
                                            <div v-else>
                                                <div class="font-medium text-sm">Wallet Transfer</div>
                                                <div class="text-xs text-muted-foreground">Internal</div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge v-if="transaction.disbursement && getRail(transaction.disbursement)" :variant="getRailVariant(getRail(transaction.disbursement))" class="text-xs">
                                                {{ getRail(transaction.disbursement) }}
                                            </Badge>
                                            <span v-else class="text-xs text-muted-foreground">—</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge v-if="transaction.disbursement" :variant="getStatusVariant(transaction.disbursement.status)" class="text-xs">
                                                {{ transaction.disbursement.status }}
                                            </Badge>
                                            <Badge v-else variant="default" class="text-xs">
                                                Completed
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span v-if="transaction.disbursement" class="font-mono text-xs text-muted-foreground">
                                                {{ getTransactionId(transaction.disbursement) }}
                                            </span>
                                            <span v-else class="text-xs text-muted-foreground">—</span>
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
            <!-- End Vouchers View -->

            <!-- Wallet Transactions View -->
            <div v-if="activeTab === 'wallet'" class="space-y-6">
            <!-- Wallet Stats Cards -->
            <div v-if="walletLoading" class="flex justify-center py-8">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Transactions</CardTitle>
                        <Receipt class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ walletStats.total }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ walletStats.deposits }} deposits • {{ walletStats.withdrawals }} withdrawals
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Deposits</CardTitle>
                        <ArrowDownLeft class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ formatAmount(walletStats.totalDeposits, walletStats.currency) }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Withdrawals</CardTitle>
                        <ArrowUpRight class="h-4 w-4 text-red-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-red-600">{{ formatAmount(walletStats.totalWithdrawals, walletStats.currency) }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Today</CardTitle>
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ walletStats.today }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Transactions today
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Wallet Table Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Wallet Transactions</CardTitle>
                            <CardDescription>{{ walletPagination.total }} transactions found</CardDescription>
                        </div>
                    </div>
                    
                    <!-- Wallet Filters -->
                    <div class="space-y-4 pt-4">
                        <div class="grid gap-4 sm:grid-cols-4">
                            <div class="relative sm:col-span-2">
                                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    v-model="walletSearchQuery"
                                    placeholder="Search by sender, voucher code... (auto-search)"
                                    class="pl-8"
                                />
                            </div>
                            <Input
                                v-model="walletDateFrom"
                                type="date"
                                placeholder="From date"
                            />
                            <Input
                                v-model="walletDateTo"
                                type="date"
                                placeholder="To date"
                            />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <select
                                v-model="walletTypeFilter"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="all">All Transactions</option>
                                <option value="deposit">Deposits Only</option>
                                <option value="withdraw">Withdrawals Only</option>
                            </select>
                            <div></div>
                            <div></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        <Button @click="clearWalletFilters" variant="outline" size="sm" :disabled="walletLoading">
                            Clear
                        </Button>
                        <div></div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Wallet Transactions Table -->
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-left">Details</th>
                                    <th class="px-4 py-3 text-left">Payment Method / Rail</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="walletLoading">
                                    <td colspan="5" class="px-4 py-8 text-center">
                                        <Loader2 class="inline h-6 w-6 animate-spin text-muted-foreground" />
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="tx in walletTransactions"
                                        :key="tx.id"
                                        class="border-b hover:bg-muted/50 cursor-pointer transition-colors"
                                        @click="openWalletTransactionDetail(tx)"
                                    >
                                        <td class="px-4 py-3">
                                            <Badge :variant="tx.type === 'deposit' ? 'default' : 'secondary'" class="text-xs">
                                                {{ tx.type === 'deposit' ? 'Deposit' : 'Withdrawal' }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold" :class="tx.type === 'deposit' ? 'text-green-600' : 'text-red-600'">
                                            {{ tx.type === 'deposit' ? '+' : '-' }}{{ formatAmount(tx.amount, tx.currency) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div v-if="tx.type === 'deposit'">
                                                <div class="font-medium text-sm">
                                                    {{ tx.sender_name || 'Top-Up' }}
                                                </div>
                                                <div v-if="tx.sender_identifier" class="text-xs text-muted-foreground font-mono">
                                                    {{ tx.sender_identifier }}
                                                </div>
                                                <div v-if="tx.deposit_type" class="text-xs text-muted-foreground capitalize">
                                                    {{ tx.deposit_type.replace('_', ' ') }}
                                                </div>
                                            </div>
                                            <div v-else>
                                                <div class="font-medium text-sm font-mono">
                                                    {{ tx.voucher_code || 'Voucher Generation' }}
                                                </div>
                                                <div v-if="tx.disbursement" class="text-xs text-muted-foreground">
                                                    {{ tx.disbursement.recipient_name }} • {{ getMaskedAccount(tx.disbursement.recipient_identifier) }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div v-if="tx.type === 'deposit' && tx.payment_method">
                                                <Badge variant="outline" class="text-xs capitalize">
                                                    {{ tx.payment_method.replace('_', ' ') }}
                                                </Badge>
                                            </div>
                                            <div v-else-if="tx.type === 'withdraw' && tx.disbursement">
                                                <Badge :variant="getRailVariant(tx.disbursement.rail)" class="text-xs">
                                                    {{ tx.disbursement.rail }}
                                                </Badge>
                                            </div>
                                            <span v-else class="text-xs text-muted-foreground">—</span>
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">
                                            {{ formatDate(tx.created_at) }}
                                        </td>
                                    </tr>
                                    <tr v-if="walletTransactions.length === 0">
                                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                            No wallet transactions found
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Wallet Pagination -->
                    <div v-if="walletPagination.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (walletPagination.current_page - 1) * walletPagination.per_page + 1 }} to
                            {{ Math.min(walletPagination.current_page * walletPagination.per_page, walletPagination.total) }}
                            of {{ walletPagination.total }} results
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-for="link in walletPaginationLinks"
                                :key="link.label"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="link.disabled || walletLoading"
                                @click="goToWalletPage(link.page)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>
            <!-- End Wallet Transactions View -->

            <!-- Wallet Transaction Detail Modal -->
            <WalletTransactionDetailModal
                :transaction="selectedWalletTransaction"
                :open="isWalletDetailModalOpen"
                @update:open="isWalletDetailModalOpen = $event"
            />

            <!-- Sender Detail Modal -->
            <SenderDetailModal
                :sender-id="selectedSenderId"
                :open="isSenderModalOpen"
                @update:open="isSenderModalOpen = $event"
            />
        </div>
    </AppLayout>
</template>
