<script setup lang="ts">
/**
 * VoucherOwnerView - Display component for voucher owner information
 * 
 * Read-only component that displays voucher owner details.
 * 
 * @component
 * @example
 * <VoucherOwnerView v-if="voucher.owner" :owner="voucher.owner" />
 */
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { User } from 'lucide-vue-next';

interface OwnerData {
    name: string;
    email: string;
    mobile?: string;
}

interface Props {
    owner: OwnerData;
}

const props = defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Owner Information</CardTitle>
            <CardDescription>Details about the voucher owner</CardDescription>
        </CardHeader>
        <CardContent>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="flex items-center text-sm font-medium text-muted-foreground">
                        <User class="mr-2 h-4 w-4" />
                        Name
                    </dt>
                    <dd class="mt-1 text-sm">{{ owner.name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-muted-foreground">Email</dt>
                    <dd class="mt-1 text-sm">{{ owner.email }}</dd>
                </div>
                <div v-if="owner.mobile">
                    <dt class="text-sm font-medium text-muted-foreground">Mobile</dt>
                    <dd class="mt-1 text-sm">{{ owner.mobile }}</dd>
                </div>
            </dl>
        </CardContent>
    </Card>
</template>
