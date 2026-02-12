<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Wallet, Plus, Clock } from 'lucide-vue-next';

interface TopUp {
    reference: string;
    amount: number;
    currency: string;
    status: string;
    created_at: string;
}

interface Props {
    balance: number | string;
    formattedBalance: string;
    currency: string;
    topUps: TopUp[];
}

const props = defineProps<Props>();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatAmount = (amount: number | string | null | undefined) => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    const validNum = typeof num === 'number' && !isNaN(num) ? num : 0;
    return validNum.toFixed(2);
};

const getStatusBadge = (status: string | null) => {
    if (!status) return 'default';
    
    switch (status.toLowerCase()) {
        case 'paid':
        case 'completed':
            return 'success';
        case 'pending':
            return 'warning';
        case 'failed':
            return 'destructive';
        default:
            return 'default';
    }
};
</script>

<template>
    <PwaLayout title="Wallet">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-2">
                    <Wallet class="h-6 w-6 text-primary" />
                    <h1 class="text-lg font-semibold">Wallet</h1>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4 space-y-4">
            <!-- Balance Card -->
            <Card>
                <CardContent class="pt-6">
                    <div class="text-center space-y-4">
                        <div>
                            <p class="text-sm text-muted-foreground">Available Balance</p>
                            <div class="text-4xl font-bold mt-2">{{ currency }} {{ formattedBalance }}</div>
                        </div>
                        <Button as-child class="w-full" size="lg">
                            <Link href="/load">
                                <Plus class="mr-2 h-5 w-5" />
                                Add Funds
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Top-Up History -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Clock class="h-5 w-5 text-primary" />
                        <CardTitle class="text-base">Top-Up History</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="topUps.length === 0" class="py-8 text-center">
                        <Clock class="mx-auto h-12 w-12 text-muted-foreground/50" />
                        <h3 class="mt-4 text-sm font-medium">No top-ups yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Your top-up history will appear here.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="topUp in topUps"
                            :key="topUp.reference"
                            class="flex items-center justify-between p-3 rounded-lg border"
                        >
                            <div class="flex-1">
                                <div class="font-medium text-sm">{{ topUp.reference }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ formatDate(topUp.created_at) }}
                                </div>
                            </div>
                            <div class="text-right space-y-1">
                                <div class="font-semibold text-sm text-green-600">
                                    +{{ topUp.currency }} {{ formatAmount(topUp.amount) }}
                                </div>
                                <Badge :variant="getStatusBadge(topUp.status)" class="text-xs">
                                    {{ topUp.status }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PwaLayout>
</template>
