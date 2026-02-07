<script setup lang="ts">
import { computed } from 'vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { FileText, CheckSquare, Signal, Shield } from 'lucide-vue-next';
import type { DriverSummary } from '@/types/envelope';

interface Props {
    modelValue: string; // Format: "driver_id@version"
    drivers: DriverSummary[];
    disabled?: boolean;
    placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    placeholder: 'Select a driver...',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const selectedDriver = computed(() => {
    if (!props.modelValue) return null;
    const [id, version] = props.modelValue.split('@');
    return props.drivers.find(d => d.id === id && d.version === version) ?? null;
});

const handleChange = (value: string) => {
    emit('update:modelValue', value);
};
</script>

<template>
    <div class="space-y-3">
        <Select 
            :model-value="modelValue" 
            @update:model-value="handleChange"
            :disabled="disabled"
        >
            <SelectTrigger class="w-full">
                <SelectValue :placeholder="placeholder" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="driver in drivers"
                    :key="`${driver.id}@${driver.version}`"
                    :value="`${driver.id}@${driver.version}`"
                >
                    <div class="flex flex-col py-1">
                        <span class="font-medium">{{ driver.title }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ driver.id }}@{{ driver.version }}
                        </span>
                    </div>
                </SelectItem>
            </SelectContent>
        </Select>

        <!-- Selected driver summary -->
        <div v-if="selectedDriver" class="flex flex-wrap gap-2 text-xs">
            <Badge variant="outline" class="gap-1">
                <FileText class="h-3 w-3" />
                {{ selectedDriver.documents_count }} docs
            </Badge>
            <Badge variant="outline" class="gap-1">
                <CheckSquare class="h-3 w-3" />
                {{ selectedDriver.checklist_count }} checklist
            </Badge>
            <Badge variant="outline" class="gap-1">
                <Signal class="h-3 w-3" />
                {{ selectedDriver.signals_count }} signals
            </Badge>
            <Badge variant="outline" class="gap-1">
                <Shield class="h-3 w-3" />
                {{ selectedDriver.gates_count }} gates
            </Badge>
        </div>

        <!-- Driver description -->
        <p v-if="selectedDriver?.description" class="text-sm text-muted-foreground">
            {{ selectedDriver.description }}
        </p>
    </div>
</template>
