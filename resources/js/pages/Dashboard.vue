<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    useDashboardApi,
    type DashboardStats,
    type RecentActivity as RecentActivityData,
} from '@/composables/useDashboardApi';
import StatCard from '@/components/dashboard/StatCard.vue';
import QuickActions from '@/components/dashboard/QuickActions.vue';
import RecentActivityComponent from '@/components/dashboard/RecentActivity.vue';
import Heading from '@/components/Heading.vue';
import {
    Ticket,
    Clock,
    TicketCheck,
    DollarSign,
    ArrowDownLeft,
    Wallet,
    TrendingUp,
    FileText,
    CircleDollarSign,
    Activity,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const { loading, getStats, getActivity } = useDashboardApi();
const stats = ref<DashboardStats | null>(null);
const activity = ref<RecentActivityData | null>(null);

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('en-PH').format(num);
};

const loadDashboardData = async () => {
    const [statsData, activityData] = await Promise.all([
        getStats(),
        getActivity(),
    ]);

    stats.value = statsData;
    activity.value = activityData;
};

onMounted(() => {
    loadDashboardData();
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                title="Dashboard"
                description="Overview of your voucher operations"
            />

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Total Vouchers"
                    :value="formatNumber(stats?.vouchers.total || 0)"
                    :icon="Ticket"
                    :loading="loading"
                    href="/vouchers"
                />
                <StatCard
                    title="Active Vouchers"
                    :value="formatNumber(stats?.vouchers.active || 0)"
                    :icon="Clock"
                    :loading="loading"
                    href="/vouchers"
                />
                <StatCard
                    title="Redeemed"
                    :value="formatNumber(stats?.vouchers.redeemed || 0)"
                    subtitle="Total redemptions"
                    :icon="TicketCheck"
                    :loading="loading"
                    href="/transactions"
                />
                <StatCard
                    title="Wallet Balance"
                    :value="
                        formatAmount(
                            stats?.wallet.balance || 0,
                            stats?.wallet.currency || 'PHP',
                        )
                    "
                    :icon="Wallet"
                    :loading="loading"
                    href="/wallet"
                />
            </div>

            <!-- Secondary Stats -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    title="This Month (Disbursements)"
                    :value="
                        formatAmount(
                            stats?.transactions.total_amount || 0,
                            stats?.transactions.currency || 'PHP',
                        )
                    "
                    :subtitle="`${stats?.transactions.this_month || 0} transactions`"
                    :icon="DollarSign"
                    :loading="loading"
                />
                <StatCard
                    title="This Month (Deposits)"
                    :value="
                        formatAmount(
                            stats?.deposits.total_amount || 0,
                            stats?.deposits.currency || 'PHP',
                        )
                    "
                    :subtitle="`${stats?.deposits.unique_senders || 0} unique senders`"
                    :icon="ArrowDownLeft"
                    :loading="loading"
                />
                <StatCard
                    title="Success Rate"
                    :value="`${stats?.disbursements.success_rate || 0}%`"
                    :subtitle="`${stats?.disbursements.successful || 0} of ${stats?.disbursements.total_attempts || 0} attempts`"
                    :icon="TrendingUp"
                    :loading="loading"
                />
            </div>
            
            <!-- Settlement Vouchers Stats -->
            <div v-if="stats?.settlements" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    title="Settlement Vouchers"
                    :value="formatNumber(stats.settlements.total_vouchers || 0)"
                    :subtitle="`${stats.settlements.active_count || 0} active, ${stats.settlements.closed_count || 0} closed`"
                    :icon="FileText"
                    :loading="loading"
                    href="/vouchers?type=settlement"
                />
                <StatCard
                    title="Amount Collected"
                    :value="
                        formatAmount(
                            stats.settlements.total_collected || 0,
                            stats.settlements.currency || 'PHP',
                        )
                    "
                    :subtitle="`Target: ${formatAmount(
                        stats.settlements.total_target || 0,
                        stats.settlements.currency || 'PHP',
                    )}`"
                    :icon="CircleDollarSign"
                    :loading="loading"
                />
                <StatCard
                    title="Collection Rate"
                    :value="
                        stats.settlements.total_target > 0
                            ? `${Math.round((stats.settlements.total_collected / stats.settlements.total_target) * 100)}%`
                            : '0%'
                    "
                    :subtitle="`${stats.settlements.total_payable || 0} payable, ${stats.settlements.total_settlement || 0} settlement`"
                    :icon="Activity"
                    :loading="loading"
                />
            </div>

            <!-- Quick Actions -->
            <QuickActions />

            <!-- Recent Activity -->
            <RecentActivityComponent :activity="activity" :loading="loading" />
        </div>
    </AppLayout>
</template>
