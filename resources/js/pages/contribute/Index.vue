<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { useToast } from '@/components/ui/toast/use-toast'
import { 
    FileUp, 
    CheckCircle2, 
    Clock, 
    XCircle, 
    Upload, 
    FileText, 
    ChevronDown,
    User,
    Calendar,
    Loader2,
    Pencil,
    Database,
    ListPlus,
    Trash2
} from 'lucide-vue-next'

interface DocumentType {
    code: string
    label: string
    description: string
    required: boolean
    max_files: number
}

interface Attachment {
    id: number
    file_name: string
    doc_type: string
    review_status: string
    uploaded_at: string
    url: string
}

interface PayloadSchemaField {
    key: string
    label: string
    required: boolean
    pointer: string
}

interface Props {
    voucher: {
        code: string
        type: string
        amount: number
        currency: string
    }
    envelope: {
        id: number
        status: string
        payload: Record<string, any> | null
    }
    token: {
        uuid: string
        label: string | null
        recipient_name: string | null
        expires_at: string
    }
    document_types: DocumentType[]
    existing_attachments: Attachment[]
    payload_schema_fields?: PayloadSchemaField[]
    config: {
        max_file_size: number
        allowed_mime_types: string[]
    }
}

const props = defineProps<Props>()

const page = usePage()
const { toast } = useToast()

const selectedFile = ref<File | null>(null)
const uploading = ref(false)
const uploadedAttachments = ref<Attachment[]>([...props.existing_attachments])
const updatingPayload = ref(false)
const deleting = ref<number | null>(null)

// Get count of uploads per document type
const uploadCountByType = computed(() => {
    const counts: Record<string, number> = {}
    for (const att of uploadedAttachments.value) {
        counts[att.doc_type] = (counts[att.doc_type] || 0) + 1
    }
    return counts
})

// Filter document types to only show ones that can still accept uploads
const availableDocTypes = computed(() => {
    return props.document_types.filter(dt => {
        const currentCount = uploadCountByType.value[dt.code] || 0
        return currentCount < dt.max_files
    })
})

// Selected document type (default to first available)
const selectedDocType = ref(availableDocTypes.value[0]?.code || '')

// Check if uploads are disabled (no available document types)
const uploadsDisabled = computed(() => availableDocTypes.value.length === 0)

const fileInput = ref<HTMLInputElement | null>(null)

// Payload editing state
const isEditingPayload = ref(false)
const payloadFields = reactive<Record<string, string>>({})
const newFieldKey = ref('')
const newFieldValue = ref('')

const expiresAt = computed(() => {
    const date = new Date(props.token.expires_at)
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
})

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    accepted: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
}

const statusIcons: Record<string, any> = {
    pending: Clock,
    accepted: CheckCircle2,
    rejected: XCircle,
}

function formatCurrency(amount: number, currency: string = 'PHP') {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(amount)
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function handleFileSelect(event: Event) {
    const input = event.target as HTMLInputElement
    if (input.files && input.files[0]) {
        const file = input.files[0]
        
        // Validate file size
        if (file.size > props.config.max_file_size) {
            toast({
                title: 'File too large',
                description: `Maximum file size is ${formatFileSize(props.config.max_file_size)}`,
                variant: 'destructive',
            })
            return
        }
        
        // Validate file type
        if (!props.config.allowed_mime_types.includes(file.type)) {
            toast({
                title: 'Invalid file type',
                description: 'Please upload a PDF or image file (JPEG, PNG, GIF)',
                variant: 'destructive',
            })
            return
        }
        
        selectedFile.value = file
    }
}

function clearFile() {
    selectedFile.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

async function uploadDocument() {
    if (!selectedFile.value || !selectedDocType.value) return
    
    uploading.value = true
    
    const formData = new FormData()
    formData.append('token', props.token.uuid)
    formData.append('doc_type', selectedDocType.value)
    formData.append('file', selectedFile.value)
    
    try {
        const csrfToken = (page.props as any).csrf_token || 
                          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        const response = await fetch('/contribute/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
        })
        
        const data = await response.json()
        
        if (!response.ok) {
            throw new Error(data.error || 'Upload failed')
        }
        
        // Add to list
        uploadedAttachments.value.push(data.attachment)
        
        // Clear form
        clearFile()
        
        // Update selected doc type if current one is now full
        const currentType = props.document_types.find(dt => dt.code === selectedDocType.value)
        const currentCount = uploadCountByType.value[selectedDocType.value] || 0
        if (currentType && currentCount >= currentType.max_files) {
            // Switch to next available type
            const nextAvailable = availableDocTypes.value[0]
            if (nextAvailable) {
                selectedDocType.value = nextAvailable.code
            }
        }
        
        toast({
            title: 'Document Uploaded',
            description: 'Your document has been uploaded and is pending review.',
        })
    } catch (err: any) {
        toast({
            title: 'Upload Failed',
            description: err.message || 'Failed to upload document',
            variant: 'destructive',
        })
    } finally {
        uploading.value = false
    }
}

// Payload editing functions
function formatPayloadValue(value: any): string {
    if (value === null || value === undefined) return '-'
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
}

function startPayloadEdit() {
    // Copy current payload to edit fields
    Object.keys(props.envelope.payload || {}).forEach(key => {
        payloadFields[key] = formatPayloadValue(props.envelope.payload![key])
    })
    isEditingPayload.value = true
}

function cancelPayloadEdit() {
    isEditingPayload.value = false
    // Reset fields
    Object.keys(payloadFields).forEach(key => delete payloadFields[key])
    newFieldKey.value = ''
    newFieldValue.value = ''
}

function addPayloadField() {
    const key = newFieldKey.value.trim()
    if (key && !payloadFields[key]) {
        payloadFields[key] = newFieldValue.value
        newFieldKey.value = ''
        newFieldValue.value = ''
    }
}

function removePayloadField(key: string) {
    delete payloadFields[key]
}

function populateFromSchema() {
    if (!props.payload_schema_fields?.length) return
    
    // Add each schema field if not already present
    for (const field of props.payload_schema_fields) {
        if (!payloadFields[field.key]) {
            payloadFields[field.key] = ''
        }
    }
}

const hasSchemaFields = computed(() => (props.payload_schema_fields?.length ?? 0) > 0)

// Delete attachment (only pending ones)
async function deleteAttachment(attachment: Attachment) {
    if (attachment.review_status !== 'pending') {
        toast({
            title: 'Cannot Delete',
            description: 'Only pending documents can be deleted.',
            variant: 'destructive',
        })
        return
    }
    
    if (!confirm('Are you sure you want to delete this document?')) {
        return
    }
    
    deleting.value = attachment.id
    
    try {
        const csrfToken = (page.props as any).csrf_token || 
                          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        const response = await fetch('/contribute/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                token: props.token.uuid,
                attachment_id: attachment.id,
            }),
        })
        
        const data = await response.json()
        
        if (!response.ok) {
            throw new Error(data.error || 'Delete failed')
        }
        
        // Remove from list
        uploadedAttachments.value = uploadedAttachments.value.filter(a => a.id !== attachment.id)
        
        toast({
            title: 'Document Deleted',
            description: 'The document has been removed.',
        })
    } catch (err: any) {
        toast({
            title: 'Delete Failed',
            description: err.message || 'Failed to delete document',
            variant: 'destructive',
        })
    } finally {
        deleting.value = null
    }
}

// Check if an attachment can be deleted
function canDelete(attachment: Attachment): boolean {
    return attachment.review_status === 'pending'
}

async function savePayload() {
    updatingPayload.value = true
    
    try {
        // Convert fields to payload object
        const payload: Record<string, any> = {}
        Object.keys(payloadFields).forEach(key => {
            const value = payloadFields[key]
            // Try to parse JSON for objects/arrays
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
        
        const csrfToken = (page.props as any).csrf_token || 
                          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        const response = await fetch('/contribute/payload', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                token: props.token.uuid,
                payload,
            }),
        })
        
        const data = await response.json()
        
        if (!response.ok) {
            throw new Error(data.error || 'Update failed')
        }
        
        // Update local state
        props.envelope.payload = data.payload
        isEditingPayload.value = false
        
        toast({
            title: 'Reference Updated',
            description: 'Payment reference data has been updated.',
        })
    } catch (err: any) {
        toast({
            title: 'Update Failed',
            description: err.message || 'Failed to update reference data',
            variant: 'destructive',
        })
    } finally {
        updatingPayload.value = false
    }
}
</script>

<template>
    <Head title="Contribute Documents" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-lg">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="mb-3 flex justify-center">
                    <div class="h-14 w-14 bg-blue-100 rounded-full flex items-center justify-center">
                        <FileUp class="h-7 w-7 text-blue-600" />
                    </div>
                </div>
                <h1 class="text-2xl font-semibold text-gray-900">
                    Document Contribution
                </h1>
                <p class="text-gray-600 mt-1">
                    Upload documents for voucher <span class="font-mono font-medium">{{ voucher.code }}</span>
                </p>
            </div>

            <!-- Main Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- Voucher Details -->
                <div class="p-6 border-b">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                        Voucher Details
                    </h2>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Code:</span>
                            <span class="font-mono font-semibold">{{ voucher.code }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Type:</span>
                            <span class="capitalize">{{ voucher.type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount:</span>
                            <span class="font-semibold text-blue-600">
                                {{ formatCurrency(voucher.amount, voucher.currency) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Contributor Info -->
                <div v-if="token.recipient_name || token.label" class="px-6 py-4 bg-gray-50 border-b">
                    <div class="flex items-center gap-2 text-sm">
                        <User class="h-4 w-4 text-gray-400" />
                        <span v-if="token.recipient_name" class="font-medium">{{ token.recipient_name }}</span>
                        <span v-if="token.label" class="text-gray-500">{{ token.label }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-500 mt-1">
                        <Calendar class="h-3 w-3" />
                        <span>Link expires: {{ expiresAt }}</span>
                    </div>
                </div>

                <!-- Existing Documents -->
                <div v-if="uploadedAttachments.length > 0" class="p-6 border-b">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                        Uploaded Documents ({{ uploadedAttachments.length }})
                    </h2>
                    <div class="space-y-2">
                        <div
                            v-for="att in uploadedAttachments"
                            :key="att.id"
                            class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg"
                        >
                            <FileText class="h-5 w-5 text-gray-400 flex-shrink-0" />
                            <div class="flex-1 min-w-0">
                                <a 
                                    :href="att.url" 
                                    target="_blank" 
                                    class="text-sm font-medium text-gray-900 hover:text-blue-600 truncate block"
                                >
                                    {{ att.file_name }}
                                </a>
                                <div class="text-xs text-gray-500">{{ att.doc_type }}</div>
                            </div>
                            <span 
                                :class="['px-2 py-1 text-xs font-medium rounded-full', statusColors[att.review_status]]"
                            >
                                <component :is="statusIcons[att.review_status]" class="h-3 w-3 inline mr-1" />
                                {{ att.review_status }}
                            </span>
                            <!-- Delete button for pending attachments -->
                            <button
                                v-if="canDelete(att)"
                                @click="deleteAttachment(att)"
                                :disabled="deleting === att.id"
                                class="p-1 text-gray-400 hover:text-red-500 disabled:opacity-50"
                                title="Delete document"
                            >
                                <Loader2 v-if="deleting === att.id" class="h-4 w-4 animate-spin" />
                                <Trash2 v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Upload Form -->
                <div class="p-6 border-b">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Upload New Document
                    </h2>
                    
                    <!-- All uploads complete message -->
                    <div v-if="uploadsDisabled" class="text-center py-6 text-gray-500">
                        <CheckCircle2 class="h-8 w-8 mx-auto mb-2 text-green-500" />
                        <p class="text-sm font-medium text-gray-700">All documents uploaded</p>
                        <p class="text-xs mt-1">You have uploaded the maximum allowed documents for each type.</p>
                    </div>
                    
                    <div v-else class="space-y-4">
                        <!-- Document Type -->
                        <div>
                            <label for="doc_type" class="block text-sm font-medium text-gray-700 mb-1">
                                Document Type
                            </label>
                            <select
                                id="doc_type"
                                v-model="selectedDocType"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option v-for="dt in availableDocTypes" :key="dt.code" :value="dt.code">
                                    {{ dt.label }}
                                    <template v-if="dt.required"> (Required)</template>
                                    <template v-if="dt.max_files > 1"> ({{ uploadCountByType[dt.code] || 0 }}/{{ dt.max_files }})</template>
                                </option>
                            </select>
                            <p v-if="availableDocTypes.find(d => d.code === selectedDocType)?.description" class="text-xs text-gray-500 mt-1">
                                {{ availableDocTypes.find(d => d.code === selectedDocType)?.description }}
                            </p>
                        </div>

                        <!-- File Input -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                File
                            </label>
                            <div 
                                v-if="!selectedFile"
                                class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition cursor-pointer"
                                @click="fileInput?.click()"
                            >
                                <Upload class="h-8 w-8 text-gray-400 mx-auto mb-2" />
                                <p class="text-sm text-gray-600">
                                    Click to select a file
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    PDF, JPEG, PNG, GIF up to {{ formatFileSize(config.max_file_size) }}
                                </p>
                            </div>
                            <div v-else class="flex items-center gap-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <FileText class="h-8 w-8 text-blue-500 flex-shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ selectedFile.name }}</p>
                                    <p class="text-xs text-gray-500">{{ formatFileSize(selectedFile.size) }}</p>
                                </div>
                                <button 
                                    @click="clearFile"
                                    class="text-gray-400 hover:text-red-500 p-1"
                                >
                                    <XCircle class="h-5 w-5" />
                                </button>
                            </div>
                            <input
                                ref="fileInput"
                                type="file"
                                class="hidden"
                                accept=".pdf,.jpg,.jpeg,.png,.gif"
                                @change="handleFileSelect"
                            />
                        </div>

                        <!-- Upload Button -->
                        <button
                            @click="uploadDocument"
                            :disabled="uploading || !selectedFile || !selectedDocType"
                            class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition flex items-center justify-center gap-2"
                        >
                            <Loader2 v-if="uploading" class="h-5 w-5 animate-spin" />
                            <Upload v-else class="h-5 w-5" />
                            {{ uploading ? 'Uploading...' : 'Upload Document' }}
                        </button>
                    </div>
                </div>

                <!-- Reference Data Section -->
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                            Reference Data
                        </h2>
                        <div class="flex items-center gap-2">
                            <template v-if="isEditingPayload">
                                <button
                                    @click="cancelPayloadEdit"
                                    :disabled="updatingPayload"
                                    class="px-3 py-1 text-sm text-gray-600 hover:text-gray-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="savePayload"
                                    :disabled="updatingPayload"
                                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:bg-gray-400 flex items-center gap-1"
                                >
                                    <Loader2 v-if="updatingPayload" class="h-3 w-3 animate-spin" />
                                    Save
                                </button>
                            </template>
                            <button
                                v-else
                                @click="startPayloadEdit"
                                class="px-3 py-1 text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1"
                            >
                                <Pencil class="h-3 w-3" />
                                Edit
                            </button>
                        </div>
                    </div>
                    
                    <!-- Edit Mode -->
                    <div v-if="isEditingPayload" class="space-y-3">
                        <div 
                            v-for="(value, key) in payloadFields" 
                            :key="key"
                            class="flex gap-2"
                        >
                            <input
                                :value="key"
                                readonly
                                class="w-1/3 px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm"
                            />
                            <input
                                v-model="payloadFields[key]"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                :placeholder="`Enter ${key}...`"
                            />
                            <button
                                @click="removePayloadField(key)"
                                class="px-2 text-gray-400 hover:text-red-500"
                                title="Remove field"
                            >
                                <XCircle class="h-5 w-5" />
                            </button>
                        </div>
                        
                        <!-- Populate from Schema button -->
                        <div v-if="hasSchemaFields" class="pt-2">
                            <button
                                @click="populateFromSchema"
                                class="w-full px-3 py-2 text-sm border border-dashed border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 flex items-center justify-center gap-2"
                            >
                                <ListPlus class="h-4 w-4" />
                                Populate Required Fields from Schema
                            </button>
                            <p class="text-xs text-gray-500 mt-1 text-center">
                                Adds {{ payload_schema_fields?.length ?? 0 }} field(s) defined by the driver
                            </p>
                        </div>
                        
                        <!-- Add new field -->
                        <div class="flex gap-2 pt-2">
                            <input
                                v-model="newFieldKey"
                                placeholder="Field name"
                                class="w-1/3 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                @keyup.enter="addPayloadField"
                            />
                            <input
                                v-model="newFieldValue"
                                placeholder="Value"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                @keyup.enter="addPayloadField"
                            />
                            <button
                                @click="addPayloadField"
                                :disabled="!newFieldKey.trim()"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                + Add
                            </button>
                        </div>
                    </div>
                    
                    <!-- View Mode -->
                    <div v-else>
                        <div v-if="Object.keys(envelope.payload || {}).length > 0" class="space-y-2">
                            <div 
                                v-for="(value, key) in envelope.payload" 
                                :key="key"
                                class="flex justify-between py-2 border-b last:border-0"
                            >
                                <span class="text-sm text-gray-500">{{ key }}</span>
                                <span class="text-sm font-medium text-right max-w-[60%] truncate">
                                    {{ formatPayloadValue(value) }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500">
                            <Database class="h-8 w-8 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No reference data</p>
                            <button
                                @click="startPayloadEdit"
                                class="mt-2 text-sm text-blue-600 hover:text-blue-800"
                            >
                                + Add reference data
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <p class="mt-6 text-xs text-center text-gray-500">
                Documents will be reviewed before acceptance • Secure upload
            </p>
        </div>
    </div>
</template>
