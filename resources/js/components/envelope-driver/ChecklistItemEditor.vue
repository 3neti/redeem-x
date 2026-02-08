<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Trash2, CheckSquare, Info } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface ChecklistItem {
    key: string;
    label: string;
    kind: 'document' | 'payload_field' | 'signal' | 'attestation';
    doc_type?: string | null;
    payload_pointer?: string | null;
    signal_key?: string | null;
    required: boolean;
    review: 'none' | 'optional' | 'required';
}

interface DocumentType {
    type: string;
    title: string;
}

interface Signal {
    key: string;
}

interface Props {
    modelValue: ChecklistItem[];
    documentTypes?: DocumentType[];
    signals?: Signal[];
}

const props = withDefaults(defineProps<Props>(), {
    documentTypes: () => [],
    signals: () => [],
});

const emit = defineEmits<{
    'update:modelValue': [value: ChecklistItem[]];
}>();

const kindOptions = [
    { value: 'payload_field', label: 'Payload Field', description: 'Check if a payload field has value' },
    { value: 'document', label: 'Document', description: 'Check if a document is uploaded' },
    { value: 'signal', label: 'Signal', description: 'Check if a signal is set' },
    { value: 'attestation', label: 'Attestation', description: 'Require user attestation' },
];

const reviewOptions = [
    { value: 'none', label: 'None', description: 'Auto-accept when present' },
    { value: 'optional', label: 'Optional', description: 'Can be reviewed but not required' },
    { value: 'required', label: 'Required', description: 'Must be reviewed and accepted' },
];

const addItem = () => {
    emit('update:modelValue', [
        ...props.modelValue,
        {
            key: '',
            label: '',
            kind: 'payload_field',
            payload_pointer: '',
            required: true,
            review: 'none',
        },
    ]);
};

const removeItem = (index: number) => {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
};

const updateItem = (index: number, field: keyof ChecklistItem, value: any) => {
    const updated = [...props.modelValue];
    updated[index] = { ...updated[index], [field]: value };
    
    // Clear irrelevant fields when kind changes
    if (field === 'kind') {
        updated[index].doc_type = null;
        updated[index].payload_pointer = null;
        updated[index].signal_key = null;
    }
    
    emit('update:modelValue', updated);
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Info class="h-4 w-4" />
            <span>Checklist items define requirements that must be satisfied before the envelope can be settled.</span>
        </div>

        <div v-if="modelValue.length === 0" class="text-center py-8 text-muted-foreground border-2 border-dashed rounded-lg">
            <CheckSquare class="mx-auto h-8 w-8 mb-2 opacity-50" />
            <p>No checklist items defined</p>
            <Button variant="outline" size="sm" class="mt-2" @click="addItem">
                <Plus class="mr-2 h-4 w-4" />
                Add Checklist Item
            </Button>
        </div>

        <Card v-for="(item, index) in modelValue" :key="index">
            <CardContent class="pt-4 space-y-4">
                <div class="flex items-start justify-between">
                    <div class="flex-1 grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label>
                                Key
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Info class="inline h-3 w-3 ml-1 text-muted-foreground" />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p class="max-w-xs">Unique identifier for this checklist item (e.g., document_uploaded, name_provided)</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </Label>
                            <Input
                                :model-value="item.key"
                                @update:model-value="updateItem(index, 'key', $event)"
                                placeholder="e.g., document_uploaded"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Display Label</Label>
                            <Input
                                :model-value="item.label"
                                @update:model-value="updateItem(index, 'label', $event)"
                                placeholder="e.g., Document uploaded"
                            />
                        </div>
                    </div>
                    <Button variant="ghost" size="icon" class="ml-2 text-destructive" @click="removeItem(index)">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Kind</Label>
                        <Select
                            :model-value="item.kind"
                            @update:model-value="updateItem(index, 'kind', $event)"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select kind..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in kindOptions" :key="opt.value" :value="opt.value">
                                    <div>
                                        <span>{{ opt.label }}</span>
                                        <span class="text-muted-foreground ml-2 text-xs">{{ opt.description }}</span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Conditional field based on kind -->
                    <div class="space-y-2">
                        <template v-if="item.kind === 'document'">
                            <Label>Document Type</Label>
                            <Select
                                :model-value="item.doc_type || ''"
                                @update:model-value="updateItem(index, 'doc_type', $event)"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select document type..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="doc in documentTypes" :key="doc.type" :value="doc.type">
                                        {{ doc.title }} ({{ doc.type }})
                                    </SelectItem>
                                    <SelectItem v-if="documentTypes.length === 0" value="" disabled>
                                        No document types defined
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </template>

                        <template v-else-if="item.kind === 'payload_field'">
                            <Label>
                                Payload Pointer
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Info class="inline h-3 w-3 ml-1 text-muted-foreground" />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p class="max-w-xs">JSON pointer to the payload field (e.g., /name, /amount)</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </Label>
                            <Input
                                :model-value="item.payload_pointer || ''"
                                @update:model-value="updateItem(index, 'payload_pointer', $event)"
                                placeholder="e.g., /name"
                            />
                        </template>

                        <template v-else-if="item.kind === 'signal'">
                            <Label>Signal Key</Label>
                            <Select
                                :model-value="item.signal_key || ''"
                                @update:model-value="updateItem(index, 'signal_key', $event)"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select signal..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="sig in signals" :key="sig.key" :value="sig.key">
                                        {{ sig.key }}
                                    </SelectItem>
                                    <SelectItem v-if="signals.length === 0" value="" disabled>
                                        No signals defined
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </template>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="flex items-center space-x-2">
                        <Switch
                            :checked="item.required"
                            @update:checked="updateItem(index, 'required', $event)"
                        />
                        <Label>Required for settlement</Label>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label>Review Mode</Label>
                        <Select
                            :model-value="item.review"
                            @update:model-value="updateItem(index, 'review', $event)"
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in reviewOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }} - {{ opt.description }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Button v-if="modelValue.length > 0" variant="outline" @click="addItem">
            <Plus class="mr-2 h-4 w-4" />
            Add Checklist Item
        </Button>
    </div>
</template>
