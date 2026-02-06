<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { FileCheck, Clock, Lock, CheckCircle2, XCircle, AlertCircle } from 'lucide-vue-next'
import type { Envelope } from '@/composables/useEnvelope'

interface Props {
    envelope: Envelope
}

const props = defineProps<Props>()

const statusConfig = computed(() => {
    switch (props.envelope.status) {
        case 'draft':
            return { label: 'Draft', variant: 'secondary' as const, icon: FileCheck }
        case 'active':
            return { label: 'Active', variant: 'default' as const, icon: Clock }
        case 'locked':
            return { label: 'Locked', variant: 'warning' as const, icon: Lock }
        case 'settled':
            return { label: 'Settled', variant: 'success' as const, icon: CheckCircle2 }
        case 'cancelled':
            return { label: 'Cancelled', variant: 'destructive' as const, icon: XCircle }
        default:
            return { label: 'Unknown', variant: 'outline' as const, icon: AlertCircle }
    }
})

const isSettleable = computed(() => props.envelope.gates_cache?.settleable === true)

const formatDate = (dateStr?: string) => {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString()
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <component :is="statusConfig.icon" class="h-5 w-5" />
                        Settlement Envelope
                    </CardTitle>
                    <CardDescription>
                        {{ envelope.driver_id }}@{{ envelope.driver_version }}
                    </CardDescription>
                </div>
                <Badge :variant="statusConfig.variant">
                    {{ statusConfig.label }}
                </Badge>
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Key Info Grid -->
            <div class="grid gap-4 md:grid-cols-3">
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Reference</p>
                    <p class="font-mono font-medium">{{ envelope.reference_code }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Payload Version</p>
                    <p class="font-medium">v{{ envelope.payload_version }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">Settleable</p>
                    <Badge :variant="isSettleable ? 'success' : 'secondary'">
                        {{ isSettleable ? 'Yes' : 'No' }}
                    </Badge>
                </div>
            </div>

            <!-- Gates -->
            <div class="space-y-2">
                <p class="text-sm font-medium">Gates</p>
                <div class="flex flex-wrap gap-2">
                    <Badge 
                        v-for="(value, key) in envelope.gates_cache" 
                        :key="key"
                        :variant="value ? 'success' : 'outline'"
                        class="text-xs"
                    >
                        <CheckCircle2 v-if="value" class="mr-1 h-3 w-3" />
                        <XCircle v-else class="mr-1 h-3 w-3" />
                        {{ key }}
                    </Badge>
                </div>
            </div>

            <!-- Timestamps -->
            <div v-if="envelope.locked_at || envelope.settled_at" class="space-y-2 text-sm">
                <div v-if="envelope.locked_at" class="flex justify-between">
                    <span class="text-muted-foreground">Locked at</span>
                    <span>{{ formatDate(envelope.locked_at) }}</span>
                </div>
                <div v-if="envelope.settled_at" class="flex justify-between">
                    <span class="text-muted-foreground">Settled at</span>
                    <span>{{ formatDate(envelope.settled_at) }}</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
