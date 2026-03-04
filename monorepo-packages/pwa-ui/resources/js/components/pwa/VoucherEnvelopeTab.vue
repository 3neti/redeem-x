<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { FileText, Paperclip, Activity } from 'lucide-vue-next';

interface Props {
  envelope?: any;
}

const props = defineProps<Props>();

// Format date
const formatDate = (dateStr: string | null | undefined) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

// Format file size
const formatFileSize = (bytes: number | null | undefined) => {
  if (!bytes) return 'N/A';
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb.toFixed(1)} KB`;
  return `${(kb / 1024).toFixed(1)} MB`;
};
</script>

<template>
  <div class="space-y-4">
    <div v-if="!envelope" class="text-center text-muted-foreground py-8">
      No envelope data available
    </div>

    <template v-else>
      <!-- Envelope Status -->
      <Card>
        <CardHeader>
          <CardTitle class="text-lg">Envelope Status</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div>
            <div class="text-sm text-muted-foreground">Reference</div>
            <code class="text-sm font-mono">{{ envelope.reference_code }}</code>
          </div>

          <div>
            <div class="text-sm text-muted-foreground">Status</div>
            <Badge class="capitalize">{{ envelope.status }}</Badge>
          </div>

          <div>
            <div class="text-sm text-muted-foreground">Driver</div>
            <div class="text-sm font-medium">{{ envelope.driver_id }}@{{ envelope.driver_version }}</div>
          </div>

          <div>
            <div class="text-sm text-muted-foreground">Last Updated</div>
            <div class="text-sm font-medium">{{ formatDate(envelope.updated_at) }}</div>
          </div>
        </CardContent>
      </Card>

      <!-- Attachments -->
      <Card v-if="envelope.attachments && envelope.attachments.length > 0">
        <CardHeader>
          <CardTitle class="text-lg flex items-center gap-2">
            <Paperclip class="h-5 w-5" />
            Attachments ({{ envelope.attachments.length }})
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div 
            v-for="attachment in envelope.attachments" 
            :key="attachment.id"
            class="p-3 bg-muted rounded-lg"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate">{{ attachment.original_filename }}</div>
                <div class="text-xs text-muted-foreground mt-1">
                  {{ attachment.doc_type }} • {{ formatFileSize(attachment.size) }}
                </div>
                <Badge 
                  :variant="attachment.review_status === 'accepted' ? 'success' : 'secondary'" 
                  class="text-xs mt-2"
                >
                  {{ attachment.review_status }}
                </Badge>
              </div>
              <a 
                v-if="attachment.url"
                :href="attachment.url" 
                target="_blank"
                class="text-xs text-primary hover:underline whitespace-nowrap"
              >
                View
              </a>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Payload -->
      <Card v-if="envelope.payload && Object.keys(envelope.payload).length > 0">
        <CardHeader>
          <CardTitle class="text-lg flex items-center gap-2">
            <FileText class="h-5 w-5" />
            Payload
          </CardTitle>
        </CardHeader>
        <CardContent>
          <pre class="text-xs bg-muted p-3 rounded-lg overflow-x-auto">{{ JSON.stringify(envelope.payload, null, 2) }}</pre>
        </CardContent>
      </Card>

      <!-- Audit Log (condensed) -->
      <Card v-if="envelope.audit_logs && envelope.audit_logs.length > 0">
        <CardHeader>
          <CardTitle class="text-lg flex items-center gap-2">
            <Activity class="h-5 w-5" />
            Activity ({{ envelope.audit_logs.length }})
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="space-y-2">
            <div 
              v-for="log in envelope.audit_logs.slice(0, 5)" 
              :key="log.id"
              class="flex items-start gap-3 text-sm pb-2 border-b last:border-0"
            >
              <div class="flex-1">
                <div class="font-medium capitalize">{{ log.action.replace(/_/g, ' ') }}</div>
                <div class="text-xs text-muted-foreground">
                  {{ log.actor_email || 'System' }} • {{ formatDate(log.created_at) }}
                </div>
              </div>
            </div>
            <div v-if="envelope.audit_logs.length > 5" class="text-xs text-center text-muted-foreground pt-2">
              Showing 5 of {{ envelope.audit_logs.length }} entries
            </div>
          </div>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
