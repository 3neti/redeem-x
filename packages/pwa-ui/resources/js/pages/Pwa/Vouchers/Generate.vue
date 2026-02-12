<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PwaLayout from '../../../layouts/PwaLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Plus, ArrowLeft, AlertCircle } from 'lucide-vue-next';

interface Props {
    campaigns: any[];
    inputFieldOptions: any[];
}

const props = defineProps<Props>();
</script>

<template>
    <PwaLayout title="Generate Vouchers">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center gap-3 px-4 py-3">
                <Button as-child variant="ghost" size="icon">
                    <Link href="/pwa/portal">
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-lg font-semibold">Generate Vouchers</h1>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4">
            <Card class="border-blue-200 bg-blue-50">
                <CardHeader>
                    <div class="flex items-start gap-3">
                        <AlertCircle class="h-5 w-5 text-blue-600 mt-0.5" />
                        <div class="flex-1">
                            <CardTitle class="text-base text-blue-900">Coming Soon</CardTitle>
                            <CardDescription class="text-blue-700">
                                The mobile voucher generation wizard is under development. For now, please use the desktop version.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <p class="text-sm text-blue-800">
                            We're building a mobile-optimized wizard with:
                        </p>
                        <ul class="text-sm text-blue-800 space-y-1 ml-4 list-disc">
                            <li>Campaign templates for quick generation</li>
                            <li>Voucher type selection (Redeemable, Payable, Settlement)</li>
                            <li>Input field configuration with bottom sheet</li>
                            <li>Validation rules (location, time, secret)</li>
                            <li>Feedback channels setup</li>
                            <li>Real-time cost preview</li>
                        </ul>
                        <Button as-child variant="outline" class="w-full mt-4">
                            <a href="/vouchers/generate" target="_blank">
                                Open Desktop Version
                            </a>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PwaLayout>
</template>
