<script setup lang="ts">
/**
 * EnvelopeConfigCard - Configuration card for attaching settlement envelopes
 * 
 * Used in:
 * - /vouchers/generate - attach envelope during generation
 * - /vouchers/{code} - attach to existing voucher
 * - /settings/campaigns/create & edit - store config in campaign template
 */
import { computed, watch } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { FileCode2, ChevronDown } from 'lucide-vue-next';
import DriverSelector from './DriverSelector.vue';
import type { DriverSummary, EnvelopeConfig } from '@/types/envelope';

interface Props {
    modelValue: EnvelopeConfig | null;
    availableDrivers: DriverSummary[];
    readonly?: boolean;
    showPayloadForm?: boolean;
    defaultOpen?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    readonly: false,
    showPayloadForm: false,
    defaultOpen: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: EnvelopeConfig | null];
}>();

// Computed for enabled state
const isEnabled = computed({
    get: () => props.modelValue?.enabled ?? false,
    set: (value: boolean) => {
        if (value) {
            // Enable with first available driver as default
            const defaultDriver = props.availableDrivers[0];
            emit('update:modelValue', {
                enabled: true,
                driver_id: defaultDriver?.id ?? '',
                driver_version: defaultDriver?.version ?? '',
                initial_payload: {},
            });
        } else {
            emit('update:modelValue', null);
        }
    },
});

// Computed for driver key (id@version format)
const driverKey = computed({
    get: () => {
        if (!props.modelValue) return '';
        return `${props.modelValue.driver_id}@${props.modelValue.driver_version}`;
    },
    set: (value: string) => {
        if (!value || !props.modelValue) return;
        const [id, version] = value.split('@');
        emit('update:modelValue', {
            ...props.modelValue,
            driver_id: id,
            driver_version: version,
        });
    },
});

// Get selected driver details
const selectedDriver = computed(() => {
    if (!props.modelValue) return null;
    return props.availableDrivers.find(
        d => d.id === props.modelValue?.driver_id && d.version === props.modelValue?.driver_version
    ) ?? null;
});

// Get payload schema fields from selected driver
const payloadFields = computed(() => {
    const schema = selectedDriver.value?.payload_schema;
    if (!schema?.properties) return [];
    
    const required = schema.required ?? [];
    return Object.entries(schema.properties).map(([key, prop]) => ({
        key,
        type: prop.type,
        description: prop.description,
        required: required.includes(key),
    }));
});

// Update a payload field value
const updatePayloadField = (key: string, value: string) => {
    if (!props.modelValue) return;
    
    const currentPayload = props.modelValue.initial_payload ?? {};
    let parsedValue: any = value;
    
    // Type coercion based on schema type
    const field = payloadFields.value.find(f => f.key === key);
    if (field?.type === 'number' && value !== '') {
        parsedValue = Number(value);
    } else if (value === '') {
        // Remove empty values
        const { [key]: _, ...rest } = currentPayload;
        emit('update:modelValue', {
            ...props.modelValue,
            initial_payload: rest,
        });
        return;
    }
    
    emit('update:modelValue', {
        ...props.modelValue,
        initial_payload: {
            ...currentPayload,
            [key]: parsedValue,
        },
    });
};

// Get current value for a payload field
const getPayloadValue = (key: string): string => {
    const value = props.modelValue?.initial_payload?.[key];
    if (value === undefined || value === null) return '';
    return String(value);
};
</script>

<template>
    <Collapsible :default-open="defaultOpen || isEnabled" class="border rounded-lg">
        <CollapsibleTrigger class="flex w-full items-center justify-between p-4 hover:bg-muted/50 transition-colors">
            <div class="flex items-center gap-3">
                <FileCode2 class="h-5 w-5 text-muted-foreground" />
                <div class="text-left">
                    <h3 class="font-medium">Settlement Envelope</h3>
                    <p class="text-sm text-muted-foreground">
                        {{ isEnabled ? `Using ${selectedDriver?.title ?? 'driver'}` : 'Attach an evidence envelope' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div 
                    v-if="!readonly"
                    class="flex items-center gap-2"
                    @click.stop
                >
                    <Label for="envelope-enabled" class="text-sm text-muted-foreground">
                        {{ isEnabled ? 'Enabled' : 'Disabled' }}
                    </Label>
                    <Switch 
                        id="envelope-enabled"
                        :checked="isEnabled"
                        @update:checked="isEnabled = $event"
                    />
                </div>
                <ChevronDown class="h-4 w-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
            </div>
        </CollapsibleTrigger>

        <CollapsibleContent>
            <div class="px-4 pb-4 space-y-4 border-t pt-4">
                <!-- No drivers available warning -->
                <div 
                    v-if="availableDrivers.length === 0" 
                    class="text-sm text-muted-foreground text-center py-4"
                >
                    No envelope drivers available. Configure drivers in YAML files.
                </div>

                <!-- Driver selection -->
                <div v-else-if="isEnabled" class="space-y-4">
                    <div class="space-y-2">
                        <Label>Driver</Label>
                        <DriverSelector
                            v-model="driverKey"
                            :drivers="availableDrivers"
                            :disabled="readonly"
                            placeholder="Select envelope driver..."
                        />
                    </div>

                    <!-- Initial payload form -->
                    <div v-if="selectedDriver && payloadFields.length > 0" class="space-y-3">
                        <Label>Initial Payload</Label>
                        <p class="text-sm text-muted-foreground">
                            Pre-fill envelope payload values. Required fields marked with *.
                        </p>
                        <div class="grid gap-3">
                            <div v-for="field in payloadFields" :key="field.key" class="space-y-1">
                                <Label :for="`payload-${field.key}`" class="text-sm">
                                    {{ field.key }}
                                    <span v-if="field.required" class="text-destructive">*</span>
                                    <span class="text-muted-foreground font-normal ml-1">({{ field.type }})</span>
                                </Label>
                                <Input
                                    :id="`payload-${field.key}`"
                                    :type="field.type === 'number' ? 'number' : 'text'"
                                    :placeholder="field.description ?? `Enter ${field.key}`"
                                    :model-value="getPayloadValue(field.key)"
                                    @update:model-value="updatePayloadField(field.key, $event)"
                                    :disabled="readonly"
                                    class="h-9"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Disabled state info -->
                <div v-else class="text-sm text-muted-foreground">
                    Enable to attach a settlement envelope to vouchers.
                    Envelopes track evidence, approvals, and documents before settlement.
                </div>

                <!-- Footer slot for custom actions -->
                <slot name="footer" />
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
