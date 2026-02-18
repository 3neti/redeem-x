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
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-3">
                    <CardTitle>Settlement Envelope</CardTitle>
                    <Badge :variant="statusConfig.variant" class="whitespace-nowrap">
                        {{ statusConfig.label }}
                    </Badge>
                </div>
                <CardDescription>
                    Driver: {{ envelope.driver_id }}
                </CardDescription>
            </div>
        </CardHeader>
        <CardContent class="space-y-3">
            <!-- Simple Status Indicator (only show when not settleable) -->
            <div v-if="!isSettleable" class="space-y-2">
                <div v-if="!requiredPresent" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Clock class="h-4 w-4" />
                    <span>Waiting for required documents</span>
                </div>
                <div v-if="requiredPresent && !requiredAccepted" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Clock class="h-4 w-4" />
                    <span>Waiting for document review</span>
                </div>
                <div v-if="blockingSignals.length > 0" class="flex items-center gap-2 text-sm text-yellow-600 dark:text-yellow-400">
                    <AlertCircle class="h-4 w-4" />
                    <span>Blocked by {{ blockingSignals.length }} signal{{ blockingSignals.length > 1 ? 's' : '' }}</span>
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
