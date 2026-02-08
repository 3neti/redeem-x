<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Trash2, Signal, Info } from 'lucide-vue-next';

interface SignalDefinition {
    key: string;
    type: string;
    source: string;
    default: any;
    required?: boolean;
    signal_category?: string;
    system_settable?: boolean;
}

interface Props {
    modelValue: SignalDefinition[];
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:modelValue': [value: SignalDefinition[]] }>();

const addSignal = () => {
    emit('update:modelValue', [...props.modelValue, {
        key: '', type: 'boolean', source: 'host', default: false, required: false, signal_category: 'decision', system_settable: false,
    }]);
};

const removeSignal = (index: number) => {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
};

const updateSignal = (index: number, field: keyof SignalDefinition, value: any) => {
    const updated = [...props.modelValue];
    updated[index] = { ...updated[index], [field]: value };
    emit('update:modelValue', updated);
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Info class="h-4 w-4" />
            <span>Signals are boolean or string flags that can be set by the system or reviewers to indicate state.</span>
        </div>

        <div v-if="modelValue.length === 0" class="text-center py-8 text-muted-foreground border-2 border-dashed rounded-lg">
            <Signal class="mx-auto h-8 w-8 mb-2 opacity-50" />
            <p>No signals defined</p>
            <Button variant="outline" size="sm" class="mt-2" @click="addSignal">
                <Plus class="mr-2 h-4 w-4" /> Add Signal
            </Button>
        </div>

        <Card v-for="(signal, index) in modelValue" :key="index">
            <CardContent class="pt-4 space-y-4">
                <div class="flex items-start justify-between">
                    <div class="flex-1 grid gap-4 md:grid-cols-3">
                        <div class="space-y-2">
                            <Label>Key</Label>
                            <Input :model-value="signal.key" @update:model-value="updateSignal(index, 'key', $event)" placeholder="e.g., approved" />
                        </div>
                        <div class="space-y-2">
                            <Label>Type</Label>
                            <Select :model-value="signal.type" @update:model-value="updateSignal(index, 'type', $event)">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="boolean">Boolean</SelectItem>
                                    <SelectItem value="string">String</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Source</Label>
                            <Select :model-value="signal.source" @update:model-value="updateSignal(index, 'source', $event)">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="host">Host (manual)</SelectItem>
                                    <SelectItem value="integration">Integration (API)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <Button variant="ghost" size="icon" class="ml-2 text-destructive" @click="removeSignal(index)">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label>Category</Label>
                        <Select :model-value="signal.signal_category || 'decision'" @update:model-value="updateSignal(index, 'signal_category', $event)">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="decision">Decision (reviewer)</SelectItem>
                                <SelectItem value="integration">Integration (system)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-center space-x-2 pt-6">
                        <Switch :checked="signal.required ?? false" @update:checked="updateSignal(index, 'required', $event)" />
                        <Label>Required for settlement</Label>
                    </div>
                    <div class="flex items-center space-x-2 pt-6">
                        <Switch :checked="signal.system_settable ?? false" @update:checked="updateSignal(index, 'system_settable', $event)" />
                        <Label>System can auto-set</Label>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Button v-if="modelValue.length > 0" variant="outline" @click="addSignal">
            <Plus class="mr-2 h-4 w-4" /> Add Signal
        </Button>
    </div>
</template>
