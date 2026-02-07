<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Database, ChevronDown, ChevronUp, Copy, Check, Pencil, X, Save, Loader2 } from 'lucide-vue-next'
import { useToast } from '@/components/ui/toast/use-toast'

interface Props {
    payload: Record<string, any>
    version: number
    context?: Record<string, any> | null
    readonly?: boolean
    voucherCode?: string
}

const props = withDefaults(defineProps<Props>(), {
    readonly: false,
})

const emit = defineEmits<{
    'update:payload': [value: Record<string, any>]
    'saved': []
}>()

const { toast } = useToast()

const showRaw = ref(false)
const copied = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const editData = reactive<Record<string, string>>({})

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

// Edit mode functions
const startEditing = () => {
    // Copy current payload to edit data
    Object.keys(props.payload || {}).forEach(key => {
        editData[key] = formatValue(props.payload[key])
    })
    isEditing.value = true
}

const cancelEditing = () => {
    isEditing.value = false
    // Reset edit data
    Object.keys(editData).forEach(key => delete editData[key])
}

const savePayload = async () => {
    if (!props.voucherCode) {
        toast({ title: 'Error', description: 'Voucher code not available', variant: 'destructive' })
        return
    }
    
    isSaving.value = true
    
    try {
        // Convert editData to proper payload format
        const payload: Record<string, any> = {}
        Object.keys(editData).forEach(key => {
            const value = editData[key]
            // Try to parse as JSON for objects/arrays, otherwise use string
            if (value.startsWith('{') || value.startsWith('[')) {
                try {
                    payload[key] = JSON.parse(value)
                } catch {
                    payload[key] = value
                }
            } else if (value === '' || value === '-') {
                // Skip empty values
            } else if (!isNaN(Number(value)) && value.trim() !== '') {
                payload[key] = Number(value)
            } else {
                payload[key] = value
            }
        })
        
        const response = await fetch(`/api/v1/vouchers/${props.voucherCode}/envelope/payload`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ payload }),  // Wrap in 'payload' key
        })
        
        if (!response.ok) {
            const error = await response.json()
            // Build detailed error message from validation errors
            let errorMessage = error.message || 'Failed to update payload'
            if (error.errors && Array.isArray(error.errors)) {
                errorMessage = error.errors.map((e: any) => e.message || e).join('; ')
            } else if (error.errors && typeof error.errors === 'object') {
                errorMessage = Object.values(error.errors).flat().join('; ')
            }
            throw new Error(errorMessage)
        }
        
        toast({ title: 'Saved', description: 'Payload updated successfully' })
        isEditing.value = false
        emit('saved')
        emit('update:payload', payload)
        
        // Reload page to refresh data
        window.location.reload()
    } catch (error: any) {
        toast({ title: 'Error', description: error.message || 'Failed to save payload', variant: 'destructive' })
    } finally {
        isSaving.value = false
    }
}

const addField = () => {
    const key = prompt('Enter field name:')
    if (key && key.trim() && !editData[key]) {
        editData[key] = ''
    }
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
                    <!-- Edit/Save buttons -->
                    <template v-if="!readonly && voucherCode">
                        <template v-if="isEditing">
                            <Button 
                                variant="ghost" 
                                size="sm"
                                @click="cancelEditing"
                                :disabled="isSaving"
                            >
                                <X class="h-4 w-4 mr-1" />
                                Cancel
                            </Button>
                            <Button 
                                variant="default" 
                                size="sm"
                                @click="savePayload"
                                :disabled="isSaving"
                            >
                                <Loader2 v-if="isSaving" class="h-4 w-4 mr-1 animate-spin" />
                                <Save v-else class="h-4 w-4 mr-1" />
                                Save
                            </Button>
                        </template>
                        <Button 
                            v-else
                            variant="outline" 
                            size="sm"
                            @click="startEditing"
                        >
                            <Pencil class="h-4 w-4 mr-1" />
                            Edit
                        </Button>
                    </template>
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
                        v-if="!isEditing"
                    >
                        <ChevronUp v-if="showRaw" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                        {{ showRaw ? 'Hide' : 'Show' }} Raw
                    </Button>
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Edit mode -->
            <div v-if="isEditing" class="space-y-4">
                <div 
                    v-for="(value, key) in editData" 
                    :key="key"
                    class="space-y-2"
                >
                    <Label :for="`payload-${key}`" class="text-sm font-medium">{{ key }}</Label>
                    <Input 
                        :id="`payload-${key}`"
                        v-model="editData[key]"
                        :placeholder="`Enter ${key}...`"
                    />
                </div>
                <div v-if="Object.keys(editData).length === 0" class="space-y-4">
                    <p class="text-sm text-muted-foreground text-center py-4">
                        No fields defined. Add a field to start.
                    </p>
                </div>
                <Button 
                    type="button" 
                    variant="outline" 
                    size="sm" 
                    @click="addField"
                    class="w-full"
                >
                    + Add Field
                </Button>
            </div>
            
            <!-- Formatted view -->
            <div v-else-if="!showRaw" class="space-y-2">
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
                <div v-if="!payload || Object.keys(payload).length === 0" class="text-center text-muted-foreground py-4">
                    No payload data
                    <Button 
                        v-if="!readonly && voucherCode"
                        variant="link" 
                        size="sm"
                        @click="startEditing"
                        class="block mx-auto mt-2"
                    >
                        + Add payload data
                    </Button>
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
