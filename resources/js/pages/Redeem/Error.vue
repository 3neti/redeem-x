<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { XCircle } from 'lucide-vue-next';

interface Props {
    message: string;
}

const props = defineProps<Props>();
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <Card>
                <CardHeader class="text-center">
                    <div class="mb-4 flex justify-center">
                        <div class="rounded-full bg-red-100 p-4">
                            <XCircle :size="64" class="text-red-600" />
                        </div>
                    </div>
                    <CardTitle class="text-3xl">Redemption Error</CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Error Message -->>
                    <div class="rounded-md border bg-red-50 p-4 text-center">
                        <p class="text-sm font-medium text-red-900">{{ message }}</p>
                    </div>

                    <!-- Action Button -->
                    <div class="pt-4">
                        <Button class="w-full" @click="router.visit('/redeem')">
                            Try Another Voucher
                        </Button>
                    </div>

                    <!-- Help Text -->
                    <div class="rounded-md bg-gray-50 p-4 text-center text-sm text-gray-600">
                        <p>
                            If you believe this is an error, please contact support or try again later.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
