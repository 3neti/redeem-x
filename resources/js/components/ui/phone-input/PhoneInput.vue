<script setup lang="ts">
import { ref, onMounted, nextTick, watch } from 'vue';
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
const displayValue = ref('');

const handleInput = (phone: string, phoneObject: any) => {
    // Emit E.164 format if valid, otherwise emit the formatted number
    if (phoneObject?.valid && phoneObject?.number) {
        emit('update:modelValue', phoneObject.number);
    } else {
        emit('update:modelValue', phone);
    }
    
    // Update display value to strip +63 from the input field
    nextTick(() => {
        stripDialCodeFromDisplay();
    });
};

// Strip +63 from the input display while keeping it in the actual value
const stripDialCodeFromDisplay = () => {
    if (!inputRef.value) return;
    
    const inputElement = (inputRef.value.$el as HTMLElement)?.querySelector('input') as HTMLInputElement;
    if (!inputElement) return;
    
    const currentValue = inputElement.value;
    
    // If value starts with +63, remove it and format with parentheses
    if (currentValue.startsWith('+63 ')) {
        let withoutDialCode = currentValue.substring(4); // Remove '+63 '
        // Format as (917) 301 1987
        withoutDialCode = formatWithParentheses(withoutDialCode);
        displayValue.value = withoutDialCode;
        inputElement.value = withoutDialCode;
    } else if (currentValue.startsWith('+63')) {
        let withoutDialCode = currentValue.substring(3); // Remove '+63'
        // Format as (917) 301 1987
        withoutDialCode = formatWithParentheses(withoutDialCode);
        displayValue.value = withoutDialCode;
        inputElement.value = withoutDialCode;
    }
};

// Format number as (917) 301-1987
const formatWithParentheses = (number: string): string => {
    // Remove any existing formatting
    const digitsOnly = number.replace(/\D/g, '');
    
    if (digitsOnly.length >= 10) {
        // Full format: (917) 301-1987
        const firstThree = digitsOnly.substring(0, 3);
        const middleThree = digitsOnly.substring(3, 6);
        const lastFour = digitsOnly.substring(6, 10);
        return `(${firstThree}) ${middleThree}-${lastFour}`;
    } else if (digitsOnly.length >= 6) {
        // Partial format: (917) 301-xxx
        const firstThree = digitsOnly.substring(0, 3);
        const middleThree = digitsOnly.substring(3, 6);
        const rest = digitsOnly.substring(6);
        return `(${firstThree}) ${middleThree}${rest ? '-' + rest : ''}`;
    } else if (digitsOnly.length >= 3) {
        // Partial format: (917) xxx
        const firstThree = digitsOnly.substring(0, 3);
        const rest = digitsOnly.substring(3);
        return `(${firstThree})${rest ? ' ' + rest : ''}`;
    }
    
    return number;
};

// Watch for external value changes
watch(() => props.modelValue, () => {
    nextTick(() => {
        stripDialCodeFromDisplay();
    });
});

onMounted(() => {
    if (props.autofocus && inputRef.value) {
        // Find the actual input element and focus it
        const input = (inputRef.value.$el as HTMLElement)?.querySelector('input');
        if (input) {
            input.focus();
        }
    }
    
    // Add focus listener to select all on focus
    if (inputRef.value) {
        const input = (inputRef.value.$el as HTMLElement)?.querySelector('input');
        if (input) {
            input.addEventListener('focus', (event) => {
                // Use setTimeout to ensure selection happens after any internal handlers
                setTimeout(() => {
                    input.select();
                }, 0);
            });
            
            // Also handle click events (when user clicks into the field)
            input.addEventListener('click', (event) => {
                if (document.activeElement === input) {
                    setTimeout(() => {
                        input.select();
                    }, 0);
                }
            });
        }
    }
    
    // Initial strip of dial code
    nextTick(() => {
        stripDialCodeFromDisplay();
    });
});
</script>

<template>
    <div :class="cn('w-full', props.class)">
        <VueTelInput
            ref="inputRef"
            :model-value="modelValue"
            mode="international"
            :default-country="'PH'"
            :only-countries="['PH']"
            :disabled="disabled"
            :input-options="{
                placeholder: placeholder,
                required: required,
                styleClasses: 'hide-dial-code',
            }"
            :dropdown-options="{
                showDialCodeInSelection: true,
                showDialCodeInList: true,
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

/* Hide the +63 dial code from appearing in the input field */
:deep(.vti__input) {
    text-indent: 0 !important;
}
</style>

