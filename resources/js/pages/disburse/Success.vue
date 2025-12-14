<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle2 } from 'lucide-vue-next';

interface Props {
    voucher: {
        code: string;
        amount: number;
        formatted_amount: string;
        currency: string;
    };
}

defineProps<Props>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Redemption Successful" />
        <div class="w-full max-w-md">
            <Card>
                <CardHeader class="text-center">
                    <div class="flex justify-center mb-4">
                        <CheckCircle2 class="h-16 w-16 text-green-600" />
                    </div>
                    <CardTitle class="text-2xl">Redemption Successful!</CardTitle>
                    <CardDescription>
                        Your voucher has been redeemed successfully
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="border rounded-lg p-4 space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Voucher Code:</span>
                            <span class="font-mono font-semibold">{{ voucher.code }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Amount:</span>
                            <span class="text-lg font-semibold text-green-600">{{ voucher.formatted_amount }}</span>
                        </div>
                    </div>
                    
                    <p class="text-sm text-muted-foreground text-center">
                        The funds will be disbursed to your account shortly. You will receive a confirmation via SMS and email.
                    </p>
                    
                    <div class="flex gap-3">
                        <Button variant="outline" class="flex-1" @click="$inertia.visit('/')">
                            Go to Dashboard
                        </Button>
                        <Button class="flex-1" @click="$inertia.visit('/disburse')">
                            Redeem Another
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
