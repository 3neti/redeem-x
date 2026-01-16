<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { VueTelInput } from 'vue-tel-input';
import 'vue-tel-input/vue-tel-input.css';
import { cn } from '@/lib/utils';

interface Props {
    modelValue?: string;
    error?: string;
    disabled?: boolean;
    readonly?: boolean;
    required?: boolean;
    placeholder?: string;
    autofocus?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    error: '',
    disabled: false,
    readonly: false,
    required: false,
    placeholder: '09181234567',
    autofocus: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const inputRef = ref<InstanceType<typeof VueTelInput>>();

const handleInput = (phone: string, phoneObject: any) => {
    // Emit E.164 format if valid, otherwise emit the formatted number
    if (phoneObject?.valid && phoneObject?.number) {
        emit('update:modelValue', phoneObject.number);
    } else {
        emit('update:modelValue', phone);
    }
};

onMounted(() => {
    if (props.autofocus && inputRef.value) {
        // Find the actual input element and focus it
        const input = (inputRef.value.$el as HTMLElement)?.querySelector('input');
        if (input) {
            input.focus();
        }
    }
});
</script>

<template>
    <div :class="cn('w-full', props.class)">
        <VueTelInput
            ref="inputRef"
            :model-value="modelValue"
            mode="national"
            :default-country="'PH'"
            :only-countries="['PH']"
            :disabled="disabled"
            :dropdown-options="{ disabled: true }"
            :input-options="{
                placeholder: placeholder,
                readonly: readonly,
                required: required,
            }"
            :valid-characters-only="true"
            :auto-format="true"
            @update:model-value="handleInput"
        />
        <p v-if="error" class="text-sm text-red-600 mt-1">{{ error }}</p>
    </div>
</template>

<style scoped>
/* Match existing Input component styling */
:deep(.vue-tel-input) {
    border: 1px solid hsl(var(--input));
    border-radius: 0.375rem;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    background: transparent;
}

:deep(.vue-tel-input:focus-within) {
    border-color: hsl(var(--ring));
    box-shadow: 0 0 0 3px hsl(var(--ring) / 0.5);
}

:deep(.vti__input) {
    border: none;
    background: transparent;
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    height: 2.25rem;
}

:deep(.vti__input:focus) {
    outline: none;
    box-shadow: none;
}

:deep(.vti__dropdown) {
    background: transparent;
    border: none;
}

:deep(.vti__dropdown:hover) {
    background: hsl(var(--muted) / 0.5);
}
</style>

