<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import type { BreadcrumbItem, User } from '@/types';
import { useQrCode } from '@/composables/useQrCode';
import QrDisplay from '@/components/domain/QrDisplay.vue';
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
import { useToast } from '@/components/ui/toast/use-toast';
import { watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet/load' },
    { title: 'Load', href: '/wallet/load' },
];

const { formattedBalance } = useWalletBalance();
const page = usePage();
const user = page.props.auth.user as User;
const account = user.email; // Use email as account identifier
const amount = 0; // 0 amount means "any amount" for QR
const { qrCode, status, message, refresh } = useQrCode(account, amount);

const { toast } = useToast();

// Show toast notifications based on status
watch(status, (newStatus) => {
    if (newStatus === 'loading') {
        toast({
            title: 'Please wait',
            description: message.value,
            duration: 30_000, // keep visible longer
        });
    } else if (newStatus === 'success') {
        toast({
            title: 'Done',
            description: message.value || 'Operation completed.',
            variant: 'default',
        });
    } else if (newStatus === 'error') {
        toast({
            title: 'Error',
            description: message.value || 'Something went wrong.',
            variant: 'destructive',
        });
    }
});
</script>

<template>
    <Head title="Load Wallet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex justify-center p-6">
            <Card class="w-[380px]">
                <CardHeader>
                    <CardTitle>Scan to Load</CardTitle>
                    <CardDescription>
                        Balance: {{ formattedBalance }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="grid place-items-center">
                    <div
                        class="w-72 h-72 flex items-center justify-center"
                    >
                        <QrDisplay :qr-code="qrCode" class="w-full h-full" />
                    </div>
                </CardContent>

                <!-- Action Button -->
                <CardFooter>
                    <Button class="w-full" @click="refresh">
                        <RefreshCcw class="mr-2 h-4 w-4" />
                        Regenerate
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </AppLayout>
</template>
