<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

const voucherCode = ref('');
const isSubmitting = ref(false);

const handleSubmit = () => {
    if (!voucherCode.value.trim()) {
        return;
    }

    isSubmitting.value = true;
    router.visit(`/redeem/${voucherCode.value.trim()}/wallet`, {
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <PublicLayout>
        <div class="flex min-h-[80vh] items-center justify-center px-4">
            <Card class="w-full max-w-md">
                <CardHeader class="text-center">
                    <CardTitle class="text-2xl">Redeem Voucher</CardTitle>
                    <CardDescription>
                        Enter your voucher code to start the redemption process
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-4">
                        <div class="space-y-2">
                            <label for="voucher-code" class="text-sm font-medium">
                                Voucher Code
                            </label>
                            <Input
                                id="voucher-code"
                                v-model="voucherCode"
                                type="text"
                                placeholder="Enter voucher code"
                                required
                                :disabled="isSubmitting"
                                class="text-center text-lg uppercase tracking-wider"
                                @input="voucherCode = voucherCode.toUpperCase()"
                            />
                        </div>
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="!voucherCode.trim() || isSubmitting"
                        >
                            {{ isSubmitting ? 'Verifying...' : 'Continue' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
