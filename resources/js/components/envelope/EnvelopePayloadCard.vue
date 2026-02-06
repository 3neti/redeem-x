<script setup lang="ts">
import { ref } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Database, ChevronDown, ChevronUp, Copy, Check } from 'lucide-vue-next'
import { useToast } from '@/components/ui/toast/use-toast'

interface Props {
    payload: Record<string, any>
    version: number
    context?: Record<string, any> | null
}

const props = defineProps<Props>()
const { toast } = useToast()

const showRaw = ref(false)
const copied = ref(false)

const copyPayload = async () => {
    try {
        await navigator.clipboard.writeText(JSON.stringify(props.payload, null, 2))
        copied.value = true
        toast({ title: 'Copied', description: 'Payload copied to clipboard' })
        setTimeout(() => { copied.value = false }, 2000)
    } catch {
        toast({ title: 'Failed', description: 'Could not copy to clipboard', variant: 'destructive' })
    }
}

const formatValue = (value: any): string => {
    if (value === null || value === undefined) return '-'
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Database class="h-5 w-5" />
                        Payload
                    </CardTitle>
                    <CardDescription>
                        Version {{ version }}
                    </CardDescription>
                </div>
                <div class="flex items-center gap-2">
                    <Button 
                        variant="ghost" 
                        size="sm"
                        @click="copyPayload"
                    >
                        <Check v-if="copied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                    </Button>
                    <Button 
                        variant="ghost" 
                        size="sm"
                        @click="showRaw = !showRaw"
                    >
                        <ChevronUp v-if="showRaw" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                        {{ showRaw ? 'Hide' : 'Show' }} Raw
                    </Button>
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Formatted view -->
            <div v-if="!showRaw" class="space-y-2">
                <div 
                    v-for="(value, key) in payload" 
                    :key="key"
                    class="flex justify-between py-2 border-b last:border-0"
                >
                    <span class="text-sm text-muted-foreground">{{ key }}</span>
                    <span class="text-sm font-medium text-right max-w-[60%] truncate">
                        {{ formatValue(value) }}
                    </span>
                </div>
                <div v-if="Object.keys(payload).length === 0" class="text-center text-muted-foreground py-4">
                    No payload data
                </div>
            </div>

            <!-- Raw JSON view -->
            <pre 
                v-else 
                class="rounded-lg bg-muted p-4 text-xs overflow-auto max-h-64"
            >{{ JSON.stringify(payload, null, 2) }}</pre>

            <!-- Context section -->
            <div v-if="context && Object.keys(context).length > 0" class="pt-4 border-t">
                <p class="text-sm font-medium mb-2">Context</p>
                <div class="flex flex-wrap gap-2">
                    <Badge 
                        v-for="(value, key) in context" 
                        :key="key"
                        variant="outline"
                        class="text-xs"
                    >
                        {{ key }}: {{ formatValue(value) }}
                    </Badge>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
