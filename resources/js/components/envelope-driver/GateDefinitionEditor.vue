<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { Plus, Trash2, Shield, Info } from 'lucide-vue-next';

interface GateDefinition {
    key: string;
    rule: string;
}

interface Props {
    modelValue: GateDefinition[];
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:modelValue': [value: GateDefinition[]] }>();

const addGate = () => {
    emit('update:modelValue', [...props.modelValue, { key: '', rule: '' }]);
};

const removeGate = (index: number) => {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
};

const updateGate = (index: number, field: keyof GateDefinition, value: string) => {
    const updated = [...props.modelValue];
    updated[index] = { ...updated[index], [field]: value };
    emit('update:modelValue', updated);
};

const exampleRules = [
    { label: 'Payload valid', rule: 'payload.valid == true' },
    { label: 'Checklist complete', rule: 'checklist.required_accepted == true' },
    { label: 'Signal check', rule: 'signal.approved == true' },
    { label: 'Combine gates', rule: 'gate.payload_valid && gate.checklist_complete' },
];
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Info class="h-4 w-4" />
            <span>Gates are boolean conditions evaluated using expressions. The 'settleable' gate determines if the envelope can be settled.</span>
        </div>

        <div class="text-xs text-muted-foreground bg-muted p-3 rounded-lg space-y-1">
            <p class="font-medium">Rule expression examples:</p>
            <ul class="list-disc list-inside space-y-0.5">
                <li v-for="ex in exampleRules" :key="ex.label">
                    <code class="bg-background px-1 rounded">{{ ex.rule }}</code> - {{ ex.label }}
                </li>
            </ul>
        </div>

        <div v-if="modelValue.length === 0" class="text-center py-8 text-muted-foreground border-2 border-dashed rounded-lg">
            <Shield class="mx-auto h-8 w-8 mb-2 opacity-50" />
            <p>No gates defined</p>
            <Button variant="outline" size="sm" class="mt-2" @click="addGate">
                <Plus class="mr-2 h-4 w-4" /> Add Gate
            </Button>
        </div>

        <Card v-for="(gate, index) in modelValue" :key="index">
            <CardContent class="pt-4">
                <div class="flex items-start gap-4">
                    <div class="flex-1 grid gap-4 md:grid-cols-3">
                        <div class="space-y-2">
                            <Label>
                                Key
                                <span v-if="gate.key === 'settleable'" class="text-xs text-primary ml-1">(settlement gate)</span>
                            </Label>
                            <Input :model-value="gate.key" @update:model-value="updateGate(index, 'key', $event)" placeholder="e.g., settleable" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <Label>Rule Expression</Label>
                            <Input :model-value="gate.rule" @update:model-value="updateGate(index, 'rule', $event)" placeholder="e.g., gate.payload_valid && signal.approved" class="font-mono text-sm" />
                        </div>
                    </div>
                    <Button variant="ghost" size="icon" class="text-destructive" @click="removeGate(index)">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Button v-if="modelValue.length > 0" variant="outline" @click="addGate">
            <Plus class="mr-2 h-4 w-4" /> Add Gate
        </Button>
    </div>
</template>
