<script setup lang="ts">
/**
 * ExternalMetadataCard - Display component for voucher external metadata
 * 
 * Shows freeform JSON metadata with collapsible preview.
 * Used for displaying reference codes, invoice numbers, project tracking, etc.
 * 
 * @component
 * @example
 * <ExternalMetadataCard :metadata="{ reference_code: 'ABC-123', invoice: 'INV-001' }" />
 */
import { ref } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChevronDown, FileJson } from 'lucide-vue-next';

interface Props {
    metadata: Record<string, any> | null | undefined;
    title?: string;
    description?: string;
    defaultOpen?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'External Metadata',
    description: 'Additional tracking information for this voucher',
    defaultOpen: false,
});

const isOpen = ref(props.defaultOpen);
</script>

<template>
    <Card v-if="metadata && Object.keys(metadata).length > 0">
        <CardHeader>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <FileJson class="h-5 w-5" />
                    <CardTitle>{{ title }}</CardTitle>
                </div>
                <button
                    @click="isOpen = !isOpen"
                    class="rounded-md p-1 hover:bg-muted transition-colors"
                    :aria-label="isOpen ? 'Collapse metadata' : 'Expand metadata'"
                >
                    <ChevronDown 
                        class="h-4 w-4 transition-transform" 
                        :class="{ 'rotate-180': isOpen }"
                    />
                </button>
            </div>
            <CardDescription>{{ description }}</CardDescription>
        </CardHeader>
        <CardContent v-if="isOpen">
            <pre class="overflow-x-auto rounded-md bg-muted p-4 text-xs"><code>{{ JSON.stringify(metadata, null, 2) }}</code></pre>
        </CardContent>
    </Card>
</template>
