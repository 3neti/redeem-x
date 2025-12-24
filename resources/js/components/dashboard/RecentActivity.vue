<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import type { RecentActivity } from '@/composables/useDashboardApi';
import {
    TicketCheck,
    Ticket,
    ArrowDownLeft,
    Wallet,
} from 'lucide-vue-next';

interface Props {
    activity: RecentActivity | null;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusVariant = (status: string) => {
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

const allActivities = computed(() => {
    if (!props.activity) return [];

    const activities = [];

    // Add generations
    if (props.activity.generations) {
        activities.push(
            ...props.activity.generations.map((gen) => ({
                ...gen,
                icon: Ticket,
                displayText: `Generated ${gen.voucher_count} vouchers (${gen.campaign_name})`,
                timestamp: gen.generated_at,
            })),
        );
    }

    // Add redemptions
    if (props.activity.redemptions) {
        activities.push(
            ...props.activity.redemptions.map((red) => ({
                ...red,
                icon: TicketCheck,
                displayText: `Voucher ${red.code} redeemed`,
                timestamp: red.redeemed_at,
            })),
        );
    }

    // Add deposits
    if (props.activity.deposits) {
        activities.push(
            ...props.activity.deposits.map((dep) => ({
                ...dep,
                icon: ArrowDownLeft,
                displayText: `Deposit received`,
                timestamp: dep.created_at,
            })),
        );
    }

    // Add top-ups
    if (props.activity.topups) {
        activities.push(
            ...props.activity.topups.map((top) => ({
                ...top,
                icon: Wallet,
                displayText: `Wallet top-up via ${top.institution}`,
                timestamp: top.paid_at,
            })),
        );
    }

    // Sort by timestamp descending
    return activities.sort(
        (a, b) =>
            new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime(),
    );
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-4">
                <Skeleton v-for="i in 5" :key="i" class="h-16 w-full" />
            </div>

            <div v-else-if="allActivities.length === 0" class="py-8 text-center">
                <p class="text-sm text-muted-foreground">No recent activity</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="item in allActivities.slice(0, 10)"
                    :key="`${item.type}-${item.id}`"
                    class="flex items-center gap-3 rounded-lg border p-3"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                    >
                        <component
                            :is="item.icon"
                            class="h-4 w-4 text-muted-foreground"
                        />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium">{{ item.displayText }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDate(item.timestamp) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold">
                            {{ formatAmount(item.amount, item.currency) }}
                        </p>
                        <Badge
                            v-if="item.status"
                            :variant="getStatusVariant(item.status)"
                            class="text-xs"
                        >
                            {{ item.status }}
                        </Badge>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
