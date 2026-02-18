<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ChevronDown, ChevronUp, Copy, Check, Pencil, X, Save, Loader2, MoreVertical } from 'lucide-vue-next'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useToast } from '@/components/ui/toast/use-toast'
import VueJsonPretty from 'vue-json-pretty'
import 'vue-json-pretty/lib/styles.css'

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

const copied = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const showAddField = ref(false)
const newFieldName = ref('')
const newFieldValue = ref('')
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
    showAddField.value = false
    newFieldName.value = ''
    newFieldValue.value = ''
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
    if (newFieldName.value.trim() && !editData[newFieldName.value]) {
        editData[newFieldName.value] = newFieldValue.value
        newFieldName.value = ''
        newFieldValue.value = ''
        showAddField.value = false
    } else if (editData[newFieldName.value]) {
        toast({ title: 'Error', description: 'Field already exists', variant: 'destructive' })
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <CardTitle>Payload</CardTitle>
                <div class="flex items-center gap-2">
                    <!-- Edit/Save buttons -->
                    <template v-if="!readonly && voucherCode && isEditing">
                        <Button 
                            variant="ghost" 
                            size="sm"
                            @click="cancelEditing"
                            :disabled="isSaving"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                        <Button 
                            variant="default" 
                            size="sm"
                            @click="savePayload"
                            :disabled="isSaving"
                        >
                            <Loader2 v-if="isSaving" class="h-4 w-4 mr-1 animate-spin" />
                            <Save v-else class="h-4 w-4" />
                        </Button>
                    </template>
                    <!-- Dropdown menu for Copy/Edit -->
                    <DropdownMenu v-else-if="!isEditing">
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="sm">
                                <MoreVertical class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="copyPayload">
                                <Check v-if="copied" class="mr-2 h-4 w-4" />
                                <Copy v-else class="mr-2 h-4 w-4" />
                                Copy
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="!readonly && voucherCode" @click="startEditing">
                                <Pencil class="mr-2 h-4 w-4" />
                                Edit
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-3">
            <!-- Edit mode -->
            <div v-if="isEditing" class="space-y-3">
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
                <div v-if="Object.keys(editData).length === 0" class="text-center text-muted-foreground py-4">
                    No fields defined. Add a field to start.
                </div>
                
                <!-- Add field inline form -->
                <div v-if="showAddField" class="space-y-3 rounded-lg border p-3">
                    <div class="space-y-2">
                        <Label for="new-field-name" class="text-sm font-medium">Field Name</Label>
                        <Input 
                            id="new-field-name"
                            v-model="newFieldName"
                            placeholder="Enter field name..."
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="new-field-value" class="text-sm font-medium">Field Value</Label>
                        <Input 
                            id="new-field-value"
                            v-model="newFieldValue"
                            placeholder="Enter field value..."
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button 
                            type="button" 
                            variant="default" 
                            size="sm"
                            @click="addField"
                            class="flex-1"
                        >
                            Add
                        </Button>
                        <Button 
                            type="button" 
                            variant="ghost" 
                            size="sm"
                            @click="showAddField = false"
                            class="flex-1"
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
                <Button 
                    v-else
                    type="button" 
                    variant="outline" 
                    size="sm" 
                    @click="showAddField = true"
                    class="w-full"
                >
                    + Add Field
                </Button>
            </div>
            
            <!-- Collapsible JSON view -->
            <div v-else>
                <div v-if="!payload || Object.keys(payload).length === 0" class="text-center text-muted-foreground py-4">
                    No payload data
                </div>
                <div v-else class="rounded-lg border overflow-hidden">
                    <VueJsonPretty 
                        :data="payload" 
                        :deep="1"
                        :show-length="true"
                        :show-line="false"
                        :show-double-quotes="true"
                        :collapsed-on-click-brackets="true"
                        class="text-xs p-3"
                    />
                </div>
            </div>

            <!-- Context section -->
            <div v-if="context && Object.keys(context).length > 0" class="pt-3 border-t space-y-2">
                <p class="text-sm font-medium">Context</p>
                <div class="rounded-lg border overflow-hidden">
                    <VueJsonPretty 
                        :data="context" 
                        :deep="1"
                        :show-length="true"
                        :show-line="false"
                        :show-double-quotes="true"
                        :collapsed-on-click-brackets="true"
                        class="text-xs p-3"
                    />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
