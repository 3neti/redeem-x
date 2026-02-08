<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Plus, Trash2, FileText, Info } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface DocumentType {
    type: string;
    title: string;
    allowed_mimes: string[];
    max_size_mb: number;
    multiple: boolean;
}

interface Props {
    modelValue: DocumentType[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: DocumentType[]];
}>();

const mimeOptions = [
    { value: 'application/pdf', label: 'PDF' },
    { value: 'image/jpeg', label: 'JPEG' },
    { value: 'image/png', label: 'PNG' },
    { value: 'image/gif', label: 'GIF' },
    { value: 'application/msword', label: 'Word (DOC)' },
    { value: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', label: 'Word (DOCX)' },
];

const addDocument = () => {
    emit('update:modelValue', [
        ...props.modelValue,
        {
            type: '',
            title: '',
            allowed_mimes: ['application/pdf', 'image/jpeg', 'image/png'],
            max_size_mb: 10,
            multiple: false,
        },
    ]);
};

const removeDocument = (index: number) => {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
};

const updateDocument = (index: number, field: keyof DocumentType, value: any) => {
    const updated = [...props.modelValue];
    updated[index] = { ...updated[index], [field]: value };
    emit('update:modelValue', updated);
};

const toggleMime = (index: number, mime: string) => {
    const current = props.modelValue[index].allowed_mimes;
    const updated = current.includes(mime)
        ? current.filter(m => m !== mime)
        : [...current, mime];
    updateDocument(index, 'allowed_mimes', updated);
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Info class="h-4 w-4" />
            <span>Define document types that can be uploaded to envelopes using this driver.</span>
        </div>

        <div v-if="modelValue.length === 0" class="text-center py-8 text-muted-foreground border-2 border-dashed rounded-lg">
            <FileText class="mx-auto h-8 w-8 mb-2 opacity-50" />
            <p>No document types defined</p>
            <Button variant="outline" size="sm" class="mt-2" @click="addDocument">
                <Plus class="mr-2 h-4 w-4" />
                Add Document Type
            </Button>
        </div>

        <Card v-for="(doc, index) in modelValue" :key="index">
            <CardContent class="pt-4 space-y-4">
                <div class="flex items-start justify-between">
                    <div class="flex-1 grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label>
                                Type Identifier
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Info class="inline h-3 w-3 ml-1 text-muted-foreground" />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p class="max-w-xs">Unique identifier used in code (e.g., PROOF_OF_PAYMENT, ID_FRONT)</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </Label>
                            <Input
                                :model-value="doc.type"
                                @update:model-value="updateDocument(index, 'type', $event)"
                                placeholder="e.g., SUPPORTING_DOC"
                                class="uppercase"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Display Title</Label>
                            <Input
                                :model-value="doc.title"
                                @update:model-value="updateDocument(index, 'title', $event)"
                                placeholder="e.g., Supporting Document"
                            />
                        </div>
                    </div>
                    <Button variant="ghost" size="icon" class="ml-2 text-destructive" @click="removeDocument(index)">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <div class="space-y-2">
                    <Label>Allowed File Types</Label>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="mime in mimeOptions"
                            :key="mime.value"
                            :variant="doc.allowed_mimes.includes(mime.value) ? 'default' : 'outline'"
                            class="cursor-pointer"
                            @click="toggleMime(index, mime.value)"
                        >
                            {{ mime.label }}
                        </Badge>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Max File Size (MB)</Label>
                        <Input
                            type="number"
                            :model-value="doc.max_size_mb"
                            @update:model-value="updateDocument(index, 'max_size_mb', Number($event))"
                            min="1"
                            max="50"
                        />
                    </div>
                    <div class="flex items-center space-x-2 pt-6">
                        <Switch
                            :checked="doc.multiple"
                            @update:checked="updateDocument(index, 'multiple', $event)"
                        />
                        <Label>Allow multiple files</Label>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Button v-if="modelValue.length > 0" variant="outline" @click="addDocument">
            <Plus class="mr-2 h-4 w-4" />
            Add Document Type
        </Button>
    </div>
</template>
