<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { FileText } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import type { InputFields, VoucherInputFieldOption } from '@/types/voucher';

interface Props {
    modelValue: InputFields;
    inputFieldOptions: VoucherInputFieldOption[];
    validationErrors?: Record<string, string>;
    readonly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    readonly: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: InputFields];
}>();

const localValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const isFieldSelected = (fieldValue: string) => {
    return localValue.value.fields.includes(fieldValue as any);
};

const toggleField = (fieldValue: string) => {
    if (props.readonly) return;

    const currentFields = [...localValue.value.fields];
    const index = currentFields.indexOf(fieldValue as any);

    if (index > -1) {
        currentFields.splice(index, 1);
    } else {
        currentFields.push(fieldValue as any);
    }

    localValue.value = {
        ...localValue.value,
        fields: currentFields,
    };
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center gap-2">
                <FileText class="h-5 w-5" />
                <CardTitle>Input Fields</CardTitle>
            </div>
            <CardDescription>
                Select which information to collect during redemption
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="option in inputFieldOptions"
                    :key="option.value"
                    class="flex items-center space-x-2"
                >
                    <Checkbox
                        :id="`input_field_${option.value}`"
                        :checked="isFieldSelected(option.value)"
                        :disabled="readonly"
                        @update:checked="() => toggleField(option.value)"
                    />
                    <Label
                        :for="`input_field_${option.value}`"
                        class="text-sm font-normal cursor-pointer"
                        :class="{ 'cursor-not-allowed opacity-50': readonly }"
                    >
                        {{ option.label }}
                    </Label>
                </div>
            </div>

            <InputError :message="validationErrors['inputs.fields']" />

            <div v-if="localValue.fields.length === 0" class="text-sm text-muted-foreground">
                No input fields selected. Voucher can be redeemed without additional information.
            </div>

            <div v-else class="text-sm text-muted-foreground">
                <strong>{{ localValue.fields.length }}</strong> field(s) selected
            </div>
        </CardContent>
    </Card>
</template>
