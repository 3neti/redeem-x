<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { 
    FileEdit, Clock, Lock, CheckCircle2, XCircle, AlertCircle, 
    ClipboardCheck, ShieldCheck, RotateCcw, Ban, Send
} from 'lucide-vue-next'
import type { Envelope, EnvelopeStatus } from '@/composables/useEnvelope'
import { STATUS_CONFIG } from '@/composables/useEnvelope'

interface Props {
    envelope: Envelope
    showActions?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    showActions: false
})

const emit = defineEmits<{
    lock: []
    settle: []
    cancel: [reason: string]
    reopen: [reason: string]
}>()

// Status icon mapping for all 9 states
const statusIcons: Record<EnvelopeStatus, any> = {
    draft: FileEdit,
    in_progress: Clock,
    ready_for_review: ClipboardCheck,
    ready_to_settle: ShieldCheck,
    locked: Lock,
    settled: CheckCircle2,
    cancelled: Ban,
    rejected: XCircle,
    reopened: RotateCcw,
}

const statusConfig = computed(() => {
    const status = props.envelope.status
    const config = STATUS_CONFIG[status] ?? STATUS_CONFIG.draft
    return {
        ...config,
        icon: statusIcons[status] ?? AlertCircle
    }
})

const isSettleable = computed(() => 
    props.envelope.computed_flags?.settleable ?? 
    props.envelope.gates_cache?.settleable === true
)

// Computed flags from backend
const requiredPresent = computed(() => props.envelope.computed_flags?.required_present ?? false)
const requiredAccepted = computed(() => props.envelope.computed_flags?.required_accepted ?? false)
const blockingSignals = computed(() => props.envelope.computed_flags?.blocking_signals ?? [])

// Status helpers from backend
const canLock = computed(() => props.envelope.status_helpers?.can_lock ?? props.envelope.status === 'ready_to_settle')
const canSettle = computed(() => props.envelope.status_helpers?.can_settle ?? props.envelope.status === 'locked')
const canCancel = computed(() => props.envelope.status_helpers?.can_cancel ?? false)
const canReopen = computed(() => props.envelope.status_helpers?.can_reopen ?? false)
const isTerminal = computed(() => props.envelope.status_helpers?.is_terminal ?? ['settled', 'cancelled', 'rejected'].includes(props.envelope.status))

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

            <!-- State Machine Progress (computed flags) -->
            <div class="space-y-2">
                <p class="text-sm font-medium">Progress</p>
                <div class="flex flex-wrap gap-2">
                    <Badge :variant="requiredPresent ? 'success' : 'outline'" class="text-xs">
                        <CheckCircle2 v-if="requiredPresent" class="mr-1 h-3 w-3" />
                        <Clock v-else class="mr-1 h-3 w-3" />
                        Required Present
                    </Badge>
                    <Badge :variant="requiredAccepted ? 'success' : 'outline'" class="text-xs">
                        <CheckCircle2 v-if="requiredAccepted" class="mr-1 h-3 w-3" />
                        <Clock v-else class="mr-1 h-3 w-3" />
                        Required Accepted
                    </Badge>
                    <Badge :variant="isSettleable ? 'success' : 'outline'" class="text-xs">
                        <CheckCircle2 v-if="isSettleable" class="mr-1 h-3 w-3" />
                        <Clock v-else class="mr-1 h-3 w-3" />
                        Settleable
                    </Badge>
                </div>
            </div>

            <!-- Blocking Signals (if any) -->
            <div v-if="blockingSignals.length > 0" class="space-y-2">
                <p class="text-sm font-medium text-yellow-600 dark:text-yellow-400">Blocking Signals</p>
                <div class="flex flex-wrap gap-2">
                    <Badge 
                        v-for="signal in blockingSignals" 
                        :key="signal"
                        variant="warning"
                        class="text-xs"
                    >
                        <AlertCircle class="mr-1 h-3 w-3" />
                        {{ signal }}
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
            <div v-if="envelope.locked_at || envelope.settled_at || envelope.cancelled_at || envelope.rejected_at" class="space-y-2 text-sm">
                <div v-if="envelope.locked_at" class="flex justify-between">
                    <span class="text-muted-foreground">Locked at</span>
                    <span>{{ formatDate(envelope.locked_at) }}</span>
                </div>
                <div v-if="envelope.settled_at" class="flex justify-between">
                    <span class="text-muted-foreground">Settled at</span>
                    <span>{{ formatDate(envelope.settled_at) }}</span>
                </div>
                <div v-if="envelope.cancelled_at" class="flex justify-between">
                    <span class="text-muted-foreground">Cancelled at</span>
                    <span>{{ formatDate(envelope.cancelled_at) }}</span>
                </div>
                <div v-if="envelope.rejected_at" class="flex justify-between">
                    <span class="text-muted-foreground">Rejected at</span>
                    <span>{{ formatDate(envelope.rejected_at) }}</span>
                </div>
            </div>

            <!-- Action Buttons Slot (Phase 5) -->
            <slot name="actions" :can-lock="canLock" :can-settle="canSettle" :can-cancel="canCancel" :can-reopen="canReopen" :is-terminal="isTerminal" />
        </CardContent>
    </Card>
</template>
