<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { BarChart3, CheckCircle2, TrendingUp, Ticket } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    stats: {
        active_vouchers_count: number;
        redeemed_this_month_count: number;
        total_issued_this_month: number;
        formatted_total_issued_this_month: string;
    };
}

const props = defineProps<Props>();

// Format large numbers with k/M suffix
const formatCompactNumber = (value: number): string => {
    if (value >= 1_000_000) {
        return `₱${(value / 1_000_000).toFixed(1)}M`;
    }
    if (value >= 1_000) {
        return `₱${(value / 1_000).toFixed(1)}k`;
    }
    return `₱${value.toFixed(2)}`;
};

const compactIssuedAmount = computed(() => formatCompactNumber(props.stats.total_issued_this_month));
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <BarChart3 class="h-5 w-5 text-primary" />
                    <CardTitle class="text-base">Voucher Stats</CardTitle>
                </div>
                <Button as-child variant="ghost" size="sm">
                    <Link href="/pwa/vouchers">View All</Link>
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-3 gap-4">
                <!-- Active Vouchers -->
                <div class="text-center">
                    <div class="flex justify-center mb-2">
                        <Ticket class="h-8 w-8 text-blue-500" />
                    </div>
                    <div class="text-2xl font-bold">{{ stats.active_vouchers_count }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Active</div>
                </div>

                <!-- Redeemed This Month -->
                <div class="text-center">
                    <div class="flex justify-center mb-2">
                        <CheckCircle2 class="h-8 w-8 text-green-500" />
                    </div>
                    <div class="text-2xl font-bold">{{ stats.redeemed_this_month_count }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Redeemed</div>
                </div>

                <!-- Total Issued This Month -->
                <div class="text-center">
                    <div class="flex justify-center mb-2">
                        <TrendingUp class="h-8 w-8 text-orange-500" />
                    </div>
                    <div class="text-2xl font-bold" :title="'₱' + stats.formatted_total_issued_this_month">
                        {{ compactIssuedAmount }}
                    </div>
                    <div class="text-xs text-muted-foreground mt-1">Issued</div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
