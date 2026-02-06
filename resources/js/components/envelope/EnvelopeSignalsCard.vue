<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { Signal, CheckCircle2, XCircle } from 'lucide-vue-next'
import type { EnvelopeSignal } from '@/composables/useEnvelope'

interface Props {
    signals: EnvelopeSignal[]
    readonly?: boolean
}

withDefaults(defineProps<Props>(), {
    readonly: true
})

const emit = defineEmits<{
    toggle: [key: string, value: boolean]
}>()

const getBoolValue = (signal: EnvelopeSignal): boolean => {
    if (signal.type === 'boolean') {
        return signal.value === 'true' || signal.value === '1'
    }
    return !!signal.value
}

const handleToggle = (signal: EnvelopeSignal, newValue: boolean) => {
    emit('toggle', signal.key, newValue)
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
                <div 
                    v-for="signal in signals" 
                    :key="signal.id"
                    class="flex items-center justify-between"
                >
                    <div class="space-y-0.5">
                        <Label class="text-sm font-medium">{{ signal.key }}</Label>
                        <p class="text-xs text-muted-foreground">
                            Source: {{ signal.source }} • Type: {{ signal.type }}
                        </p>
                    </div>
                    
                    <!-- Boolean signals show as switch or badge -->
                    <template v-if="signal.type === 'boolean'">
                        <Switch 
                            v-if="!readonly"
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
        </CardContent>
    </Card>
</template>
