<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { Signal, CheckCircle2, XCircle, AlertTriangle, Bot, User, Lock } from 'lucide-vue-next'
import type { EnvelopeSignal } from '@/composables/useEnvelope'

interface Props {
    signals: EnvelopeSignal[]
    readonly?: boolean
    blockingSignals?: string[]  // From computed_flags
}

const props = withDefaults(defineProps<Props>(), {
    readonly: true,
    blockingSignals: () => []
})

const emit = defineEmits<{
    toggle: [key: string, value: boolean]
}>()

// Group signals by category
const integrationSignals = computed(() => 
    props.signals.filter(s => s.signal_category === 'integration')
)
const decisionSignals = computed(() => 
    props.signals.filter(s => s.signal_category === 'decision' || !s.signal_category)
)

const getBoolValue = (signal: EnvelopeSignal): boolean => {
    if (signal.type === 'boolean') {
        return signal.value === 'true' || signal.value === '1'
    }
    return !!signal.value
}

const isBlocking = (signal: EnvelopeSignal): boolean => {
    return props.blockingSignals.includes(signal.key)
}

const canToggle = (signal: EnvelopeSignal): boolean => {
    if (props.readonly) return false
    // System-settable signals can only be toggled by system, not UI
    if (signal.system_settable) return false
    return signal.type === 'boolean'
}

const handleToggle = (signal: EnvelopeSignal, newValue: boolean) => {
    if (canToggle(signal)) {
        emit('toggle', signal.key, newValue)
    }
}

const getCategoryIcon = (category?: string) => {
    return category === 'integration' ? Bot : User
}

const getCategoryLabel = (category?: string) => {
    return category === 'integration' ? 'System' : 'Decision'
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Signal class="h-5 w-5" />
                Signals
            </CardTitle>
            <CardDescription>
                {{ signals.length }} signal{{ signals.length !== 1 ? 's' : '' }} configured
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="signals.length === 0" class="text-center text-muted-foreground py-4">
                No signals configured
            </div>
            <div v-else class="space-y-4">
                <!-- Decision Signals (Reviewer-settable) -->
                <div v-if="decisionSignals.length > 0" class="space-y-3">
                    <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                        <User class="h-3 w-3" />
                        Decision Signals
                    </div>
                    <div 
                        v-for="signal in decisionSignals" 
                        :key="signal.id"
                        :class="[
                            'flex items-center justify-between rounded-lg border p-3',
                            isBlocking(signal) ? 'border-yellow-200 dark:border-yellow-900 bg-yellow-50/50 dark:bg-yellow-900/10' : ''
                        ]"
                    >
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">{{ signal.key }}</Label>
                                <Badge v-if="signal.required" variant="outline" class="text-xs">Required</Badge>
                                <Badge v-if="isBlocking(signal)" variant="warning" class="text-xs">
                                    <AlertTriangle class="mr-1 h-3 w-3" />
                                    Blocking
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Source: {{ signal.source }}
                            </p>
                        </div>
                        
                        <!-- Boolean signals show as switch or badge -->
                        <template v-if="signal.type === 'boolean'">
                            <Switch 
                                v-if="canToggle(signal)"
                                :checked="getBoolValue(signal)"
                                @update:checked="(val) => handleToggle(signal, val)"
                            />
                            <Badge 
                                v-else
                                :variant="getBoolValue(signal) ? 'success' : 'secondary'"
                            >
                                <CheckCircle2 v-if="getBoolValue(signal)" class="mr-1 h-3 w-3" />
                                <XCircle v-else class="mr-1 h-3 w-3" />
                                {{ getBoolValue(signal) ? 'True' : 'False' }}
                            </Badge>
                        </template>
                        
                        <!-- Non-boolean signals show value -->
                        <Badge v-else variant="outline">
                            {{ signal.value || '(empty)' }}
                        </Badge>
                    </div>
                </div>

                <!-- Integration Signals (System-settable) -->
                <div v-if="integrationSignals.length > 0" class="space-y-3">
                    <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                        <Bot class="h-3 w-3" />
                        Integration Signals
                        <span class="text-[10px] opacity-70">(System-managed)</span>
                    </div>
                    <div 
                        v-for="signal in integrationSignals" 
                        :key="signal.id"
                        class="flex items-center justify-between rounded-lg border bg-muted/30 p-3"
                    >
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium text-muted-foreground">{{ signal.key }}</Label>
                                <Lock class="h-3 w-3 text-muted-foreground" title="System-managed" />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Source: {{ signal.source }}
                            </p>
                        </div>
                        
                        <Badge 
                            :variant="getBoolValue(signal) ? 'success' : 'secondary'"
                            class="opacity-75"
                        >
                            <CheckCircle2 v-if="getBoolValue(signal)" class="mr-1 h-3 w-3" />
                            <XCircle v-else class="mr-1 h-3 w-3" />
                            {{ getBoolValue(signal) ? 'True' : 'False' }}
                        </Badge>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
