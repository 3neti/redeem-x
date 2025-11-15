<script setup lang="ts">
import { ref } from 'vue';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Eye } from 'lucide-vue-next';

interface Props {
    modelValue: string;
    merchantName?: string;
    merchantCity?: string;
    appName?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '{name} - {city}',
    merchantName: 'Sample Merchant',
    merchantCity: 'Manila',
    appName: 'redeem-x',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'preview': [];
}>();

// Preview state
const showPreview = ref(false);
const previewText = ref('');

// Generate preview and trigger save
const generatePreview = () => {
    let result = props.modelValue || '{name}';
    result = result.replace(/{name}/g, props.merchantName);
    result = result.replace(/{city}/g, props.merchantCity);
    result = result.replace(/{app_name}/g, props.appName);
    result = result.toUpperCase(); // NetBank displays in uppercase
    
    // GCash has a 25-character limit for merchant names
    const maxLength = 25;
    if (result.length > maxLength) {
        result = result.substring(0, maxLength);
    }
    
    previewText.value = result;
    showPreview.value = true;
    
    // Emit preview event to trigger save in parent
    emit('preview');
};
</script>

<template>
    <div class="space-y-4">
        <div>
            <Label for="template-input" class="mb-2 block text-sm font-medium">
                Merchant Name Template
            </Label>
            <div class="flex gap-2">
                <Input
                    id="template-input"
                    :model-value="modelValue"
                    :disabled="disabled"
                    placeholder="e.g., {name} - {city}"
                    @update:model-value="emit('update:modelValue', $event)"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    :disabled="disabled"
                    @click="generatePreview"
                >
                    <Eye class="h-4 w-4" />
                </Button>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                Available variables: <code class="rounded bg-muted px-1 py-0.5">{name}</code>,
                <code class="rounded bg-muted px-1 py-0.5">{city}</code>,
                <code class="rounded bg-muted px-1 py-0.5">{app_name}</code>
            </p>
            <p class="mt-1 text-xs text-amber-600">
                ⚠️ GCash displays max 25 characters
            </p>
        </div>

        <!-- Preview (only shown after clicking preview button) -->
        <div v-if="showPreview" class="rounded-md border bg-muted/50 p-3">
            <Label class="mb-1 block text-xs text-muted-foreground">Preview (as shown in GCash - 25 char limit)</Label>
            <p class="font-mono text-sm font-semibold">{{ previewText }}</p>
            <p class="mt-1 text-xs text-muted-foreground">{{ previewText.length }} / 25 characters</p>
        </div>
    </div>
</template>
