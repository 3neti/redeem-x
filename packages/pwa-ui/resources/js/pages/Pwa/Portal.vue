<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import VoucherCard from '../../components/VoucherCard.vue';
import { Wallet, Plus, Ticket, AlertCircle } from 'lucide-vue-next';

interface Voucher {
    code: string;
    amount: number;
    target_amount?: number | null;
    voucher_type: 'redeemable' | 'payable' | 'settlement';
    currency: string;
    status: string;
    redeemed_at: string | null;
    created_at: string;
}

interface Props {
    balance: number | string;
    formattedBalance: string;
    currency: string;
    recentVouchers: Voucher[];
    onboarding: {
        hasMobile: boolean;
        hasMerchant: boolean;
        hasBalance: boolean;
        isComplete: boolean;
    };
}

const props = defineProps<Props>();

// All formatting logic now in VoucherCard component
</script>

<template>
    <PwaLayout title="Home">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center justify-between px-4 py-3">
                <div>
                    <h1 class="text-lg font-semibold">Redeem-X</h1>
                    <p class="text-xs text-muted-foreground">Mobile Voucher Platform</p>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4 space-y-4">
            <!-- Wallet Balance Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Wallet class="h-5 w-5 text-primary" />
                            <CardTitle class="text-base">Wallet Balance</CardTitle>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div>
                            <div class="text-3xl font-bold">₱{{ formattedBalance }}</div>
                            <p class="text-sm text-muted-foreground mt-1">Available balance</p>
                        </div>
                        <div class="flex gap-2">
                            <Button as-child class="flex-1">
                                <Link href="/pwa/vouchers/generate">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Generate Voucher
                                </Link>
                            </Button>
                            <Button as-child variant="outline" class="flex-1">
                                <Link href="/pwa/topup">
                                    <Wallet class="mr-2 h-4 w-4" />
                                    Add Funds
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Onboarding Cards -->
            <Card v-if="!onboarding.hasMobile" class="border-orange-200 bg-orange-50">
                <CardHeader class="pb-3">
                    <div class="flex items-start gap-3">
                        <AlertCircle class="h-5 w-5 text-orange-600 mt-0.5" />
                        <div class="flex-1">
                            <CardTitle class="text-base text-orange-900">Complete Your Profile</CardTitle>
                            <CardDescription class="text-orange-700">
                                Add your mobile number to receive notifications
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/settings/profile">Add Mobile Number</Link>
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="!onboarding.hasMerchant" class="border-blue-200 bg-blue-50">
                <CardHeader class="pb-3">
                    <div class="flex items-start gap-3">
                        <AlertCircle class="h-5 w-5 text-blue-600 mt-0.5" />
                        <div class="flex-1">
                            <CardTitle class="text-base text-blue-900">Complete Merchant Profile</CardTitle>
                            <CardDescription class="text-blue-700">
                                Set up your merchant information to start issuing vouchers
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/settings/merchant">Setup Merchant</Link>
                    </Button>
                </CardContent>
            </Card>

            <!-- Recent Vouchers -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Ticket class="h-5 w-5 text-primary" />
                            <CardTitle class="text-base">Recent Vouchers</CardTitle>
                        </div>
                        <Button as-child variant="ghost" size="sm">
                            <Link href="/pwa/vouchers">View All</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="recentVouchers.length === 0" class="py-8 text-center">
                        <Ticket class="mx-auto h-12 w-12 text-muted-foreground/50" />
                        <h3 class="mt-4 text-sm font-medium">No vouchers yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Generate your first voucher to get started.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <Link
                            v-for="voucher in recentVouchers"
                            :key="voucher.code"
                            :href="`/pwa/vouchers/${voucher.code}`"
                            class="block"
                        >
                            <VoucherCard :voucher="voucher" />
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PwaLayout>
</template>
