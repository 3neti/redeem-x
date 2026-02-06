<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { 
    FileUp, FileCheck, Signal, Settings, Lock, CheckCircle2, XCircle, 
    PenLine, Plus, History
} from 'lucide-vue-next'
import type { EnvelopeAuditEntry } from '@/composables/useEnvelope'

interface Props {
    entries: EnvelopeAuditEntry[]
    maxEntries?: number
}

const props = withDefaults(defineProps<Props>(), {
    maxEntries: 10
})

const getActionConfig = (action: string) => {
    const configs: Record<string, { icon: any; label: string; color: string }> = {
        'envelope_created': { icon: Plus, label: 'Created', color: 'text-blue-500' },
        'payload_patch': { icon: PenLine, label: 'Payload Updated', color: 'text-purple-500' },
        'attachment_upload': { icon: FileUp, label: 'Document Uploaded', color: 'text-cyan-500' },
        'attachment_review': { icon: FileCheck, label: 'Document Reviewed', color: 'text-teal-500' },
        'signal_set': { icon: Signal, label: 'Signal Set', color: 'text-orange-500' },
        'context_update': { icon: Settings, label: 'Context Updated', color: 'text-gray-500' },
        'status_change': { icon: History, label: 'Status Changed', color: 'text-indigo-500' },
        'envelope_locked': { icon: Lock, label: 'Locked', color: 'text-yellow-500' },
        'envelope_settled': { icon: CheckCircle2, label: 'Settled', color: 'text-green-500' },
        'envelope_cancelled': { icon: XCircle, label: 'Cancelled', color: 'text-red-500' },
    }
    return configs[action] || { icon: History, label: action.replace('_', ' '), color: 'text-muted-foreground' }
}

const formatTime = (dateStr: string) => {
    return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString()
}

const displayEntries = props.entries.slice(0, props.maxEntries)
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <History class="h-5 w-5" />
                Audit Log
            </CardTitle>
            <CardDescription>
                {{ entries.length }} event{{ entries.length !== 1 ? 's' : '' }} recorded
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="displayEntries.length === 0" class="text-center text-muted-foreground py-4">
                No audit entries yet
            </div>
            <div v-else class="relative space-y-0">
                <!-- Timeline line -->
                <div class="absolute left-4 top-0 bottom-0 w-px bg-border" />
                
                <div 
                    v-for="(entry, index) in displayEntries" 
                    :key="entry.id"
                    class="relative flex gap-4 pb-4"
                >
                    <!-- Timeline dot -->
                    <div 
                        :class="[
                            'relative z-10 flex h-8 w-8 items-center justify-center rounded-full border bg-background',
                            getActionConfig(entry.action).color
                        ]"
                    >
                        <component :is="getActionConfig(entry.action).icon" class="h-4 w-4" />
                    </div>
                    
                    <!-- Content -->
                    <div class="flex-1 space-y-1 pt-1">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium">
                                {{ getActionConfig(entry.action).label }}
                            </p>
                            <span class="text-xs text-muted-foreground">
                                {{ formatTime(entry.created_at) }}
                            </span>
                        </div>
                        
                        <!-- Show before/after for certain actions -->
                        <div v-if="entry.after && Object.keys(entry.after).length > 0" class="text-xs text-muted-foreground">
                            <template v-if="entry.action === 'signal_set'">
                                {{ entry.after.key }}: {{ entry.after.value }}
                            </template>
                            <template v-else-if="entry.action === 'status_change'">
                                {{ entry.before?.status }} → {{ entry.after?.status }}
                            </template>
                            <template v-else-if="entry.action === 'attachment_review'">
                                {{ entry.after?.status }}
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            
            <div v-if="entries.length > maxEntries" class="text-center pt-2">
                <Badge variant="outline" class="text-xs">
                    +{{ entries.length - maxEntries }} more entries
                </Badge>
            </div>
        </CardContent>
    </Card>
</template>
