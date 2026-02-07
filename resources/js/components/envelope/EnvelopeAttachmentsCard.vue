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
                    <div 
                        v-for="attachment in pendingAttachments" 
                        :key="attachment.id"
                        class="flex items-center justify-between rounded-lg border border-yellow-200 dark:border-yellow-900 bg-yellow-50/50 dark:bg-yellow-900/10 p-3"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                                <component :is="isImage(attachment.mime_type) ? Image : FileText" class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div>
                                <p class="text-sm font-medium">{{ attachment.original_filename }}</p>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span>{{ attachment.doc_type }}</span>
                                    <span v-if="attachment.size">• {{ formatSize(attachment.size) }}</span>
                                    <span>•</span>
                                    <span>{{ formatDate(attachment.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="warning">
                                <Eye class="mr-1 h-3 w-3" />
                                Pending Review
                            </Badge>
                            <Button
                                v-if="attachment.url"
                                variant="ghost"
                                size="sm"
                                as="a"
                                :href="attachment.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="h-8 w-8 p-0"
                                :title="isImage(attachment.mime_type) ? 'View image' : 'Download file'"
                            >
                                <component :is="isImage(attachment.mime_type) ? Image : Download" class="h-4 w-4" />
                            </Button>
                            <!-- Action slot for Phase 5 review buttons -->
                            <slot 
                                name="review-actions" 
                                :attachment="attachment"
                                :can-review="true"
                            />
                        </div>
                    </div>
                </div>

                <!-- Reviewed Section -->
                <div v-if="reviewedAttachments.length > 0" class="space-y-2">
                    <p v-if="pendingAttachments.length > 0" class="text-xs font-medium text-muted-foreground uppercase tracking-wider mt-4">Reviewed</p>
                    <div 
                        v-for="attachment in reviewedAttachments" 
                        :key="attachment.id"
                        class="flex items-center justify-between rounded-lg border p-3"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                                <component :is="isImage(attachment.mime_type) ? Image : FileText" class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div>
                                <p class="text-sm font-medium">{{ attachment.original_filename }}</p>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span>{{ attachment.doc_type }}</span>
                                    <span v-if="attachment.size">• {{ formatSize(attachment.size) }}</span>
                                    <span>•</span>
                                    <span>{{ formatDate(attachment.created_at) }}</span>
                                </div>
                                <!-- Show rejection reason if rejected -->
                                <p v-if="attachment.review_status === 'rejected' && attachment.rejection_reason" 
                                   class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                    <AlertCircle class="h-3 w-3" />
                                    {{ attachment.rejection_reason }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :variant="getStatusConfig(attachment.review_status).variant">
                                <component 
                                    :is="getStatusConfig(attachment.review_status).icon" 
                                    class="mr-1 h-3 w-3" 
                                />
                                {{ getStatusConfig(attachment.review_status).label }}
                            </Badge>
                            <Button
                                v-if="attachment.url"
                                variant="ghost"
                                size="sm"
                                as="a"
                                :href="attachment.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="h-8 w-8 p-0"
                                :title="isImage(attachment.mime_type) ? 'View image' : 'Download file'"
                            >
                                <component :is="isImage(attachment.mime_type) ? Image : Download" class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
