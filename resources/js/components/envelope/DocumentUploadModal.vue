<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { AlertCircle, Loader2, Upload, File, X } from 'lucide-vue-next'

interface DocumentType {
    key: string
    label: string
    doc_type: string
    required?: boolean
}

interface Props {
    open: boolean
    documentTypes: DocumentType[]
    loading?: boolean
    maxSizeMb?: number
    acceptedTypes?: string
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    maxSizeMb: 10,
    acceptedTypes: 'image/*,application/pdf,.doc,.docx,.xls,.xlsx'
})

const emit = defineEmits<{
    'update:open': [value: boolean]
    upload: [docType: string, file: File]
    cancel: []
}>()

const selectedDocType = ref<string>('')
const selectedFile = ref<File | null>(null)
const dragOver = ref(false)
const error = ref<string | null>(null)

// Get document type options from checklist items
const docTypeOptions = computed(() => {
    return props.documentTypes
        .filter(item => item.doc_type)
        .map(item => ({
            value: item.doc_type,
            label: item.label || item.doc_type,
            required: item.required,
        }))
})

const isValid = computed(() => {
    return selectedDocType.value && selectedFile.value !== null
})

const fileSizeDisplay = computed(() => {
    if (!selectedFile.value) return ''
    const bytes = selectedFile.value.size
    const kb = bytes / 1024
    if (kb < 1024) return `${kb.toFixed(1)} KB`
    return `${(kb / 1024).toFixed(2)} MB`
})

watch(() => props.open, (open) => {
    if (open) {
        selectedDocType.value = ''
        selectedFile.value = null
        error.value = null
    }
})

const validateFile = (file: File): boolean => {
    error.value = null
    
    // Check file size
    const maxBytes = props.maxSizeMb * 1024 * 1024
    if (file.size > maxBytes) {
        error.value = `File is too large. Maximum size is ${props.maxSizeMb}MB`
        return false
    }
    
    return true
}

const handleFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement
    if (input.files && input.files[0]) {
        const file = input.files[0]
        if (validateFile(file)) {
            selectedFile.value = file
        }
    }
}

const handleDrop = (event: DragEvent) => {
    event.preventDefault()
    dragOver.value = false
    
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        const file = event.dataTransfer.files[0]
        if (validateFile(file)) {
            selectedFile.value = file
        }
    }
}

const handleDragOver = (event: DragEvent) => {
    event.preventDefault()
    dragOver.value = true
}

const handleDragLeave = () => {
    dragOver.value = false
}

const clearFile = () => {
    selectedFile.value = null
    error.value = null
}

const handleUpload = () => {
    if (isValid.value && selectedFile.value) {
        emit('upload', selectedDocType.value, selectedFile.value)
    }
}

const handleCancel = () => {
    emit('cancel')
    emit('update:open', false)
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Upload Document</DialogTitle>
                <DialogDescription>
                    Select a document type and upload a file to attach to this envelope.
                </DialogDescription>
            </DialogHeader>
            
            <div class="space-y-4 py-4">
                <!-- Document Type Select -->
                <div class="space-y-2">
                    <Label for="doc-type">Document Type</Label>
                    <Select v-model="selectedDocType">
                        <SelectTrigger id="doc-type">
                            <SelectValue placeholder="Select document type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem 
                                v-for="option in docTypeOptions" 
                                :key="option.value" 
                                :value="option.value"
                            >
                                {{ option.label }}
                                <span v-if="option.required" class="text-xs text-muted-foreground ml-1">(required)</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                
                <!-- File Drop Zone -->
                <div class="space-y-2">
                    <Label>File</Label>
                    <div
                        v-if="!selectedFile"
                        :class="[
                            'border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors',
                            dragOver 
                                ? 'border-primary bg-primary/5' 
                                : 'border-muted-foreground/25 hover:border-muted-foreground/50'
                        ]"
                        @drop="handleDrop"
                        @dragover="handleDragOver"
                        @dragleave="handleDragLeave"
                        @click="($refs.fileInput as HTMLInputElement).click()"
                    >
                        <Upload class="mx-auto h-8 w-8 text-muted-foreground mb-2" />
                        <p class="text-sm text-muted-foreground">
                            Drag and drop a file here, or click to select
                        </p>
                        <p class="text-xs text-muted-foreground/70 mt-1">
                            Max {{ maxSizeMb }}MB
                        </p>
                        <input
                            ref="fileInput"
                            type="file"
                            class="hidden"
                            :accept="acceptedTypes"
                            @change="handleFileSelect"
                        />
                    </div>
                    
                    <!-- Selected File Preview -->
                    <div 
                        v-else
                        class="flex items-center justify-between rounded-lg border p-3 bg-muted/30"
                    >
                        <div class="flex items-center gap-3">
                            <File class="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium truncate max-w-[200px]">
                                    {{ selectedFile.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ fileSizeDisplay }}
                                </p>
                            </div>
                        </div>
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            class="h-8 w-8 p-0"
                            @click="clearFile"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                
                <!-- Error Message -->
                <p v-if="error" class="text-sm text-red-500 flex items-center gap-1">
                    <AlertCircle class="h-3 w-3" />
                    {{ error }}
                </p>
            </div>
            
            <DialogFooter>
                <Button variant="outline" @click="handleCancel" :disabled="loading">
                    Cancel
                </Button>
                <Button @click="handleUpload" :disabled="loading || !isValid">
                    <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                    <Upload v-else class="mr-2 h-4 w-4" />
                    Upload
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
