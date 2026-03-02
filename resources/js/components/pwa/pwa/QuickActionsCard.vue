<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Plus, Ticket, Wallet, History, Settings, Zap } from 'lucide-vue-next';
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center gap-2">
                <Zap class="h-5 w-5 text-primary" />
                <CardTitle class="text-base">Quick Actions</CardTitle>
            </div>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-3 gap-4">
                <!-- Generate Voucher -->
                <Link href="/pwa/vouchers/generate" class="flex flex-col items-center gap-2 p-3 rounded-lg hover:bg-accent transition-colors">
                    <div class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center">
                        <Plus class="h-6 w-6 text-primary" />
                    </div>
                    <span class="text-xs font-medium text-center">Generate</span>
                </Link>

                <!-- View Vouchers -->
                <Link href="/pwa/vouchers" class="flex flex-col items-center gap-2 p-3 rounded-lg hover:bg-accent transition-colors">
                    <div class="h-12 w-12 rounded-full bg-blue-500/10 flex items-center justify-center">
                        <Ticket class="h-6 w-6 text-blue-500" />
                    </div>
                    <span class="text-xs font-medium text-center">Vouchers</span>
                </Link>

                <!-- Top Up -->
                <Link href="/pwa/topup" class="flex flex-col items-center gap-2 p-3 rounded-lg hover:bg-accent transition-colors">
                    <div class="h-12 w-12 rounded-full bg-green-500/10 flex items-center justify-center">
                        <Wallet class="h-6 w-6 text-green-500" />
                    </div>
                    <span class="text-xs font-medium text-center">Top Up</span>
                </Link>

                <!-- Wallet (History) -->
                <Link href="/pwa/wallet" class="flex flex-col items-center gap-2 p-3 rounded-lg hover:bg-accent transition-colors">
                    <div class="h-12 w-12 rounded-full bg-orange-500/10 flex items-center justify-center">
                        <History class="h-6 w-6 text-orange-500" />
                    </div>
                    <span class="text-xs font-medium text-center">History</span>
                </Link>

                <!-- Settings -->
                <Link href="/pwa/settings" class="flex flex-col items-center gap-2 p-3 rounded-lg hover:bg-accent transition-colors">
                    <div class="h-12 w-12 rounded-full bg-purple-500/10 flex items-center justify-center">
                        <Settings class="h-6 w-6 text-purple-500" />
                    </div>
                    <span class="text-xs font-medium text-center">Settings</span>
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
