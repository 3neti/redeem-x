<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { FileText, CheckCircle2, XCircle, Clock, Paperclip, ExternalLink, Download, Image, Eye, AlertCircle } from 'lucide-vue-next'
import type { EnvelopeAttachment, ReviewStatus } from '@/composables/useEnvelope'

interface Props {
    attachments: EnvelopeAttachment[]
    readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    readonly: true
})

const emit = defineEmits<{
    accept: [attachmentId: number]
    reject: [attachmentId: number, reason: string]
    preview: [attachment: EnvelopeAttachment]
}>()

// Group attachments by status for better organization
const pendingAttachments = computed(() => props.attachments.filter(a => a.review_status === 'pending'))
const reviewedAttachments = computed(() => props.attachments.filter(a => a.review_status !== 'pending'))

const getStatusConfig = (status: ReviewStatus) => {
    switch (status) {
        case 'accepted':
            return { icon: CheckCircle2, variant: 'success' as const, label: 'Accepted' }
        case 'rejected':
            return { icon: XCircle, variant: 'destructive' as const, label: 'Rejected' }
        default:
            return { icon: Clock, variant: 'secondary' as const, label: 'Pending Review' }
    }
}

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString()
}

const formatSize = (bytes?: number) => {
    if (!bytes) return ''
    const kb = bytes / 1024
    if (kb < 1024) return `${kb.toFixed(1)} KB`
    return `${(kb / 1024).toFixed(1)} MB`
}

const isImage = (mimeType?: string) => mimeType?.startsWith('image/')
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Paperclip class="h-5 w-5" />
                        Attachments
                    </CardTitle>
                    <CardDescription>
                        {{ attachments.length }} document{{ attachments.length !== 1 ? 's' : '' }} uploaded
                    </CardDescription>
                </div>
                <slot name="upload-action" />
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="attachments.length === 0" class="text-center text-muted-foreground py-4">
                No attachments uploaded yet
            </div>
            <div v-else class="space-y-3">
                <!-- Pending Review Section -->
                <div v-if="pendingAttachments.length > 0" class="space-y-2">
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Pending Review</p>
                    <a 
                        v-for="attachment in pendingAttachments" 
                        :key="attachment.id"
                        :href="attachment.url || '#'"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-lg border border-yellow-200 dark:border-yellow-900 bg-yellow-50/50 dark:bg-yellow-900/10 p-3 hover:bg-yellow-100/50 dark:hover:bg-yellow-900/20 transition-colors cursor-pointer"
                    >
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <component :is="isImage(attachment.mime_type) ? Image : FileText" class="h-6 w-6 text-primary" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-base font-medium capitalize">{{ attachment.doc_type.toLowerCase().replace(/_/g, ' ') }}</p>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span>{{ formatDate(attachment.created_at) }}</span>
                                    <span v-if="attachment.size">• {{ formatSize(attachment.size) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <Badge variant="warning" class="whitespace-nowrap">
                                <Eye class="mr-1 h-3 w-3" />
                                Pending Review
                            </Badge>
                            <!-- Action slot for Phase 5 review buttons -->
                            <slot 
                                name="review-actions" 
                                :attachment="attachment"
                                :can-review="true"
                            />
                        </div>
                    </a>
                </div>

                <!-- Reviewed Section -->
                <div v-if="reviewedAttachments.length > 0" class="space-y-2">
                    <p v-if="pendingAttachments.length > 0" class="text-xs font-medium text-muted-foreground uppercase tracking-wider mt-4">Reviewed</p>
                    <a 
                        v-for="attachment in reviewedAttachments" 
                        :key="attachment.id"
                        :href="attachment.url || '#'"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-lg border p-3 hover:bg-muted/50 transition-colors cursor-pointer"
                    >
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <component :is="isImage(attachment.mime_type) ? Image : FileText" class="h-6 w-6 text-primary" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-base font-medium capitalize">{{ attachment.doc_type.toLowerCase().replace(/_/g, ' ') }}</p>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span>{{ formatDate(attachment.created_at) }}</span>
                                    <span v-if="attachment.size">• {{ formatSize(attachment.size) }}</span>
                                </div>
                                <!-- Show rejection reason if rejected -->
                                <p v-if="attachment.review_status === 'rejected' && attachment.rejection_reason" 
                                   class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                    <AlertCircle class="h-3 w-3" />
                                    {{ attachment.rejection_reason }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <Badge :variant="getStatusConfig(attachment.review_status).variant" class="whitespace-nowrap">
                                <component 
                                    :is="getStatusConfig(attachment.review_status).icon" 
                                    class="mr-1 h-3 w-3" 
                                />
                                {{ getStatusConfig(attachment.review_status).label }}
                            </Badge>
                        </div>
                    </a>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
