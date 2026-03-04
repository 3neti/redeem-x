<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Bell, AlertCircle, AlertTriangle } from 'lucide-vue-next';

interface Alert {
    type: string;
    message: string;
    action: string;
    action_label: string;
}

interface Props {
    alerts: Alert[];
}

defineProps<Props>();

const getAlertIcon = (type: string) => {
    switch (type) {
        case 'low_balance':
            return AlertTriangle;
        case 'expiring_vouchers':
            return AlertCircle;
        default:
            return Bell;
    }
};

const getAlertColor = (type: string) => {
    switch (type) {
        case 'low_balance':
            return 'text-orange-600';
        case 'expiring_vouchers':
            return 'text-blue-600';
        default:
            return 'text-gray-600';
    }
};

const getAlertBgColor = (type: string) => {
    switch (type) {
        case 'low_balance':
            return 'bg-orange-50 border-orange-200';
        case 'expiring_vouchers':
            return 'bg-blue-50 border-blue-200';
        default:
            return 'bg-gray-50 border-gray-200';
    }
};
</script>

<template>
    <Card v-if="alerts.length > 0">
        <CardHeader>
            <div class="flex items-center gap-2">
                <Bell class="h-5 w-5 text-primary" />
                <CardTitle class="text-base">Pending Actions</CardTitle>
            </div>
        </CardHeader>
        <CardContent>
            <div class="space-y-3">
                <div
                    v-for="(alert, index) in alerts"
                    :key="index"
                    :class="['p-3 rounded-lg border', getAlertBgColor(alert.type)]"
                >
                    <div class="flex items-start gap-3">
                        <component
                            :is="getAlertIcon(alert.type)"
                            :class="['h-5 w-5 mt-0.5', getAlertColor(alert.type)]"
                        />
                        <div class="flex-1">
                            <p :class="['text-sm font-medium', getAlertColor(alert.type)]">
                                {{ alert.message }}
                            </p>
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="mt-2"
                            >
                                <Link :href="alert.action">
                                    {{ alert.action_label }}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
