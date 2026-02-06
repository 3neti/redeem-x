<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { FileText, CheckCircle2, XCircle, Clock, Paperclip, ExternalLink, Download, Image } from 'lucide-vue-next'
import type { EnvelopeAttachment } from '@/composables/useEnvelope'

interface Props {
    attachments: EnvelopeAttachment[]
}

defineProps<Props>()

const getStatusConfig = (status: string) => {
    switch (status) {
        case 'accepted':
            return { icon: CheckCircle2, variant: 'success' as const, label: 'Accepted' }
        case 'rejected':
            return { icon: XCircle, variant: 'destructive' as const, label: 'Rejected' }
        default:
            return { icon: Clock, variant: 'secondary' as const, label: 'Pending' }
    }
}

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString()
}

const isImage = (mimeType?: string) => mimeType?.startsWith('image/')
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Paperclip class="h-5 w-5" />
                Attachments
            </CardTitle>
            <CardDescription>
                {{ attachments.length }} document{{ attachments.length !== 1 ? 's' : '' }} uploaded
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="attachments.length === 0" class="text-center text-muted-foreground py-4">
                No attachments uploaded yet
            </div>
            <div v-else class="space-y-3">
                <div 
                    v-for="attachment in attachments" 
                    :key="attachment.id"
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                            <FileText class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <p class="text-sm font-medium">{{ attachment.original_filename }}</p>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span>{{ attachment.doc_type }}</span>
                                <span>•</span>
                                <span>{{ formatDate(attachment.created_at) }}</span>
                            </div>
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
        </CardContent>
    </Card>
</template>
