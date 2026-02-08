<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Plus, Trash2, Code, Info, Eye, Edit3 } from 'lucide-vue-next';

interface SchemaProperty {
    type: string;
    description?: string;
    default?: any;
    minimum?: number;
    format?: string;
}

interface PayloadSchema {
    type: string;
    properties: Record<string, SchemaProperty>;
    required: string[];
}

interface Props {
    modelValue: PayloadSchema | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:modelValue': [value: PayloadSchema] }>();

const activeTab = ref('visual');
const jsonError = ref('');

// Ensure we always have a valid schema
const schema = computed(() => props.modelValue ?? {
    type: 'object',
    properties: {},
    required: [],
});

// Convert schema to array of properties for editing
const properties = computed(() => {
    const props = schema.value.properties || {};
    return Object.entries(props).map(([key, value]) => ({
        key,
        ...value,
        isRequired: (schema.value.required || []).includes(key),
    }));
});

// JSON string for raw editor
const jsonString = computed(() => JSON.stringify(schema.value, null, 2));

const typeOptions = [
    { value: 'string', label: 'String' },
    { value: 'number', label: 'Number' },
    { value: 'boolean', label: 'Boolean' },
    { value: 'array', label: 'Array' },
    { value: 'object', label: 'Object' },
];

const formatOptions = [
    { value: '', label: 'None' },
    { value: 'email', label: 'Email' },
    { value: 'uri', label: 'URI' },
    { value: 'date', label: 'Date' },
    { value: 'date-time', label: 'Date-Time' },
];

const addProperty = () => {
    const newProps = { ...schema.value.properties };
    const key = `field_${Object.keys(newProps).length + 1}`;
    newProps[key] = { type: 'string' };
    emit('update:modelValue', { ...schema.value, properties: newProps });
};

const removeProperty = (key: string) => {
    const newProps = { ...schema.value.properties };
    delete newProps[key];
    const newRequired = (schema.value.required || []).filter(r => r !== key);
    emit('update:modelValue', { ...schema.value, properties: newProps, required: newRequired });
};

const updatePropertyKey = (oldKey: string, newKey: string) => {
    if (oldKey === newKey || !newKey) return;
    const newProps: Record<string, SchemaProperty> = {};
    for (const [k, v] of Object.entries(schema.value.properties || {})) {
        newProps[k === oldKey ? newKey : k] = v;
    }
    const newRequired = (schema.value.required || []).map(r => r === oldKey ? newKey : r);
    emit('update:modelValue', { ...schema.value, properties: newProps, required: newRequired });
};

const updateProperty = (key: string, field: string, value: any) => {
    const newProps = { ...schema.value.properties };
    newProps[key] = { ...newProps[key], [field]: value };
    if (value === '' || value === undefined) {
        delete newProps[key][field as keyof SchemaProperty];
    }
    emit('update:modelValue', { ...schema.value, properties: newProps });
};

const toggleRequired = (key: string, isRequired: boolean) => {
    const current = schema.value.required || [];
    const newRequired = isRequired
        ? [...current, key]
        : current.filter(r => r !== key);
    emit('update:modelValue', { ...schema.value, required: newRequired });
};

const updateFromJson = (json: string) => {
    try {
        const parsed = JSON.parse(json);
        jsonError.value = '';
        emit('update:modelValue', parsed);
    } catch (e) {
        jsonError.value = 'Invalid JSON';
    }
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Info class="h-4 w-4" />
            <span>Define the data structure for envelope payloads using JSON Schema format.</span>
        </div>

        <Tabs v-model="activeTab" class="w-full">
            <TabsList class="grid w-full grid-cols-2">
                <TabsTrigger value="visual">
                    <Edit3 class="mr-2 h-4 w-4" /> Visual Editor
                </TabsTrigger>
                <TabsTrigger value="json">
                    <Code class="mr-2 h-4 w-4" /> JSON
                </TabsTrigger>
            </TabsList>

            <TabsContent value="visual" class="space-y-4 mt-4">
                <div v-if="properties.length === 0" class="text-center py-8 text-muted-foreground border-2 border-dashed rounded-lg">
                    <Code class="mx-auto h-8 w-8 mb-2 opacity-50" />
                    <p>No properties defined</p>
                    <Button variant="outline" size="sm" class="mt-2" @click="addProperty">
                        <Plus class="mr-2 h-4 w-4" /> Add Property
                    </Button>
                </div>

                <Card v-for="prop in properties" :key="prop.key">
                    <CardContent class="pt-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 grid gap-4 md:grid-cols-4">
                                <div class="space-y-2">
                                    <Label>Field Name</Label>
                                    <Input :model-value="prop.key" @change="updatePropertyKey(prop.key, ($event.target as HTMLInputElement).value)" placeholder="e.g., name" />
                                </div>
                                <div class="space-y-2">
                                    <Label>Type</Label>
                                    <Select :model-value="prop.type" @update:model-value="updateProperty(prop.key, 'type', $event)">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>Format</Label>
                                    <Select :model-value="prop.format || ''" @update:model-value="updateProperty(prop.key, 'format', $event)">
                                        <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="opt in formatOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex items-center space-x-2 pt-6">
                                    <Switch :checked="prop.isRequired" @update:checked="toggleRequired(prop.key, $event)" />
                                    <Label>Required</Label>
                                </div>
                            </div>
                            <Button variant="ghost" size="icon" class="ml-2 text-destructive" @click="removeProperty(prop.key)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <div class="space-y-2">
                            <Label>Description (optional)</Label>
                            <Input :model-value="prop.description || ''" @update:model-value="updateProperty(prop.key, 'description', $event)" placeholder="Describe this field..." />
                        </div>
                    </CardContent>
                </Card>

                <Button v-if="properties.length > 0" variant="outline" @click="addProperty">
                    <Plus class="mr-2 h-4 w-4" /> Add Property
                </Button>
            </TabsContent>

            <TabsContent value="json" class="mt-4">
                <div class="space-y-2">
                    <Label>JSON Schema</Label>
                    <textarea
                        :value="jsonString"
                        @input="updateFromJson(($event.target as HTMLTextAreaElement).value)"
                        class="w-full h-64 font-mono text-sm p-3 border rounded-md bg-muted"
                        spellcheck="false"
                    />
                    <p v-if="jsonError" class="text-sm text-destructive">{{ jsonError }}</p>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
