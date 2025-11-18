<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { useDebounce } from '@/composables/useDebounce';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Search, Eye, TicketCheck, Clock, XCircle, ListFilter, Loader2, AlertCircle } from 'lucide-vue-next';
import { useVoucherApi, type VoucherData } from '@/composables/useVoucherApi';
import { VoucherCodeDisplay } from '@/components/voucher/views';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import ErrorBoundary from '@/components/ErrorBoundary.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '#' },
];

const { loading, error, listVouchers } = useVoucherApi();

const vouchers = ref<VoucherData[]>([]);
const pagination = ref({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
    from: 0,
    to: 0,
});

const stats = ref({
    total: 0,
    active: 0,
    redeemed: 0,
    expired: 0,
});

const searchQuery = ref('');
const debouncedSearchQuery = useDebounce(searchQuery, 500);
const selectedStatus = ref('');
const currentPage = ref(1);

const loadVouchers = async () => {
    const result = await listVouchers({
        search: debouncedSearchQuery.value || undefined,
        status: selectedStatus.value || undefined,
        page: currentPage.value,
        per_page: 15,
    });

    if (result) {
        vouchers.value = result.data;
        pagination.value = result.pagination;

        // Calculate stats from voucher data
        stats.value.total = result.pagination.total;
        stats.value.active = result.data.filter((v) => !v.is_expired && !v.is_redeemed).length;
        stats.value.redeemed = result.data.filter((v) => v.is_redeemed).length;
        stats.value.expired = result.data.filter((v) => v.is_expired && !v.is_redeemed).length;
    }
};

const applyFilters = () => {
    currentPage.value = 1;
    loadVouchers();
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedStatus.value = '';
    applyFilters();
};

const goToPage = (page: number) => {
    currentPage.value = page;
    loadVouchers();
};

// Auto-search when debounced query changes
watch(debouncedSearchQuery, () => {
    currentPage.value = 1;
    loadVouchers();
});

// Auto-filter when status changes
watch(selectedStatus, () => {
    currentPage.value = 1;
    loadVouchers();
});

onMounted(() => {
    loadVouchers();
});

const getStatusBadge = (voucher: VoucherData) => {
    if (voucher.is_redeemed) {
        return { variant: 'default' as const, label: 'Redeemed', icon: TicketCheck };
    }
    if (voucher.is_expired) {
        return { variant: 'destructive' as const, label: 'Expired', icon: XCircle };
    }
    return { variant: 'secondary' as const, label: 'Active', icon: Clock };
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
    });
};

const viewVoucher = (code: string) => {
    router.visit(`/vouchers/${code}`);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <ErrorBoundary>
            <div class="mx-auto max-w-7xl space-y-6 p-6">
                <!-- Error Alert -->
                <Alert v-if="error" variant="destructive" class="mb-4">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>Error</AlertTitle>
                    <AlertDescription>{{ error.message }}</AlertDescription>
                </Alert>
            <Heading
                title="Vouchers"
                description="Manage and track all your vouchers"
            />

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total</CardTitle>
                        <ListFilter class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            <Loader2 v-if="loading" class="h-6 w-6 animate-spin" />
                            <span v-else>{{ stats.total }}</span>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active</CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            <Loader2 v-if="loading" class="h-6 w-6 animate-spin" />
                            <span v-else>{{ stats.active }}</span>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Redeemed</CardTitle>
                        <TicketCheck class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            <Loader2 v-if="loading" class="h-6 w-6 animate-spin" />
                            <span v-else>{{ stats.redeemed }}</span>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Expired</CardTitle>
                        <XCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            <Loader2 v-if="loading" class="h-6 w-6 animate-spin" />
                            <span v-else>{{ stats.expired }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters and Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>All Vouchers</CardTitle>
                            <CardDescription>{{ pagination.total }} vouchers total</CardDescription>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="flex flex-col gap-4 pt-4 sm:flex-row">
                        <div class="relative flex-1">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by code... (auto-search enabled)"
                                class="pl-8"
                            />
                        </div>
                        <select
                            v-model="selectedStatus"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:w-40"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="redeemed">Redeemed</option>
                            <option value="expired">Expired</option>
                        </select>
                        <Button @click="clearFilters" variant="outline" :disabled="loading">
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
                                    <th class="px-4 py-3 text-left">Code</th>
                                    <th class="px-4 py-3 text-left">Amount</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Created</th>
                                    <th class="px-4 py-3 text-left">Expires</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading">
                                    <td colspan="6" class="px-4 py-8 text-center">
                                        <Loader2 class="mx-auto h-8 w-8 animate-spin text-muted-foreground" />
                                    </td>
                                </tr>
                                <tr
                                    v-else
                                    v-for="voucher in vouchers"
                                    :key="voucher.code"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="px-4 py-3">
                                        <VoucherCodeDisplay :code="voucher.code" size="sm" />
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ formatAmount(voucher.amount, voucher.currency) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge :variant="getStatusBadge(voucher).variant">
                                            <component :is="getStatusBadge(voucher).icon" class="mr-1 h-3 w-3" />
                                            {{ getStatusBadge(voucher).label }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(voucher.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ voucher.expires_at ? formatDate(voucher.expires_at) : 'Never' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            @click="viewVoucher(voucher.code)"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!loading && vouchers.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                        No vouchers found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="pagination.current_page === 1 || loading"
                                @click="goToPage(pagination.current_page - 1)"
                            >
                                Previous
                            </Button>
                            <Button
                                v-for="page in Math.min(pagination.last_page, 5)"
                                :key="page"
                                :variant="page === pagination.current_page ? 'default' : 'outline'"
                                size="sm"
                                :disabled="loading"
                                @click="goToPage(page)"
                            >
                                {{ page }}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="pagination.current_page === pagination.last_page || loading"
                                @click="goToPage(pagination.current_page + 1)"
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>
        </ErrorBoundary>
    </AppLayout>
</template>
