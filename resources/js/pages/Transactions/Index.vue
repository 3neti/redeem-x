<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { index as transactionsIndex, exportMethod as transactionsExport } from '@/actions/App/Http/Controllers/TransactionController';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, Download, Receipt, DollarSign, Calendar, TrendingUp } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface TransactionData {
    id: number;
    code: string;
    amount: number;
    currency: string;
    redeemed_at: string;
    created_at: string;
}

interface Props {
    transactions: {
        data: TransactionData[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters?: {
        search?: string;
        date_from?: string;
        date_to?: string;
    };
    stats?: {
        total: number;
        total_amount: number;
        today: number;
        this_month: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Transactions', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const applyFilters = () => {
    router.get(transactionsIndex.url(), {
        search: searchQuery.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

const exportTransactions = () => {
    const params = new URLSearchParams();
    if (searchQuery.value) params.append('search', searchQuery.value);
    if (dateFrom.value) params.append('date_from', dateFrom.value);
    if (dateTo.value) params.append('date_to', dateTo.value);
    
    window.location.href = `${transactionsExport.url()}?${params.toString()}`;
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                title="Transaction History"
                description="View and export all voucher redemptions"
            />

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Transactions</CardTitle>
                        <Receipt class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.total || 0 }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Amount</CardTitle>
                        <DollarSign class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatAmount(stats?.total_amount || 0, 'PHP') }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Today</CardTitle>
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.today || 0 }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">This Month</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.this_month || 0 }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters and Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Redemption History</CardTitle>
                            <CardDescription>{{ transactions?.total || 0 }} transactions found</CardDescription>
                        </div>
                        <Button @click="exportTransactions" variant="outline">
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
                                placeholder="Search by code..."
                                class="pl-8"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <Input
                            v-model="dateFrom"
                            type="date"
                            placeholder="From date"
                            @change="applyFilters"
                        />
                        <Input
                            v-model="dateTo"
                            type="date"
                            placeholder="To date"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button @click="applyFilters" variant="default" size="sm">
                            Apply Filters
                        </Button>
                        <Button @click="clearFilters" variant="outline" size="sm">
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
                                    <th class="px-4 py-3 text-left">Redeemed At</th>
                                    <th class="px-4 py-3 text-left">Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="transaction in transactions.data"
                                    :key="transaction.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="px-4 py-3 font-mono font-semibold">
                                        {{ transaction.code }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-green-600">
                                        {{ formatAmount(transaction.amount, transaction.currency) }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(transaction.redeemed_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(transaction.created_at) }}
                                    </td>
                                </tr>
                                <tr v-if="transactions.data.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                                        No transactions found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="transactions.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (transactions.current_page - 1) * transactions.per_page + 1 }} to
                            {{ Math.min(transactions.current_page * transactions.per_page, transactions.total) }}
                            of {{ transactions.total }} results
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-for="link in transactions.links"
                                :key="link.label"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="!link.url"
                                @click="link.url && router.visit(link.url)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
