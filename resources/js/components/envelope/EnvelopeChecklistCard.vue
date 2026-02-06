<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import Progress from '@/components/ui/progress.vue'
import { CheckCircle2, Circle, Clock, XCircle, FileText, Signal, Database } from 'lucide-vue-next'
import type { EnvelopeChecklistItem } from '@/composables/useEnvelope'

interface Props {
    items: EnvelopeChecklistItem[]
}

const props = defineProps<Props>()

const progress = computed(() => {
    const required = props.items.filter(i => i.required)
    const completed = required.filter(i => i.status === 'accepted')
    const percentage = required.length > 0 ? Math.round((completed.length / required.length) * 100) : 0
    return { required: required.length, completed: completed.length, percentage }
})

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'accepted': return CheckCircle2
        case 'rejected': return XCircle
        case 'needs_review':
        case 'uploaded': return Clock
        default: return Circle
    }
}

const getStatusColor = (status: string) => {
    switch (status) {
        case 'accepted': return 'text-green-500'
        case 'rejected': return 'text-red-500'
        case 'needs_review':
        case 'uploaded': return 'text-yellow-500'
        default: return 'text-muted-foreground'
    }
}

const getKindIcon = (kind: string) => {
    switch (kind) {
        case 'document': return FileText
        case 'signal': return Signal
        case 'payload_field': return Database
        default: return Circle
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>Checklist</CardTitle>
                    <CardDescription>
                        {{ progress.completed }}/{{ progress.required }} required items complete
                    </CardDescription>
                </div>
                <Badge :variant="progress.percentage === 100 ? 'success' : 'secondary'">
                    {{ progress.percentage }}%
                </Badge>
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Progress Bar -->
            <Progress :model-value="progress.percentage" class="h-2" />

            <!-- Items List -->
            <div class="space-y-2">
                <div 
                    v-for="item in items" 
                    :key="item.key"
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div class="flex items-center gap-3">
                        <component 
                            :is="getStatusIcon(item.status)" 
                            :class="['h-5 w-5', getStatusColor(item.status)]"
                        />
                        <div>
                            <p class="text-sm font-medium">{{ item.label }}</p>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <component :is="getKindIcon(item.kind)" class="h-3 w-3" />
                                <span>{{ item.kind.replace('_', ' ') }}</span>
                                <Badge v-if="item.required" variant="outline" class="text-xs">
                                    Required
                                </Badge>
                            </div>
                        </div>
                    </div>
                    <Badge 
                        :variant="item.status === 'accepted' ? 'success' : item.status === 'rejected' ? 'destructive' : 'secondary'"
                        class="text-xs"
                    >
                        {{ item.status.replace('_', ' ') }}
                    </Badge>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
