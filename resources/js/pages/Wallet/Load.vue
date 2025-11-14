<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import { useQrGeneration } from '@/composables/useQrGeneration';
import QrDisplay from '@/components/domain/QrDisplay.vue';
import QrSharePanel from '@/components/QrSharePanel.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { RefreshCcw } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet/load' },
    { title: 'Load', href: '/wallet/load' },
];

const { formattedBalance } = useWalletBalance();

// Use new QR generation composable
const { qrData, loading, error, regenerate } = useQrGeneration(true, 0);
</script>

<template>
    <Head title="Load Wallet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <Heading
                    title="Load Your Wallet"
                    :description="`Current Balance: ${formattedBalance}`"
                />
            </div>

            <!-- Main Content: QR Display & Share Panel -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Left Column: QR Display -->
                <div class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Your QR Code</CardTitle>
                            <CardDescription>
                                Scan this QR code to load funds
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="flex justify-center">
                            <div class="w-full max-w-sm">
                                <QrDisplay
                                    :qr-code="qrData?.qr_code ?? null"
                                    :loading="loading"
                                    :error="error"
                                />
                            </div>
                        </CardContent>

                        <!-- Regenerate Button -->
                        <CardFooter>
                            <Button
                                class="w-full"
                                variant="outline"
                                :disabled="loading"
                                @click="regenerate"
                            >
                                <RefreshCcw
                                    class="mr-2 h-4 w-4"
                                    :class="{ 'animate-spin': loading }"
                                />
                                {{ loading ? 'Generating...' : 'Regenerate QR Code' }}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Right Column: Share Panel -->
                <div>
                    <QrSharePanel :qr-data="qrData" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
