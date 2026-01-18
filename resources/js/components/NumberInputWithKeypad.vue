<script setup lang="ts">
import { ref, computed } from 'vue';
import { NumberInput } from '@/components/ui/number-input';
import NumericKeypad from '@/components/NumericKeypad.vue';

interface Props {
  modelValue?: number | null;
  defaultValue?: number;
  min?: number;
  max?: number;
  step?: number | string;
  placeholder?: string;
  disabled?: boolean;
  prefix?: string;
  suffix?: string;
  allowDecimal?: boolean;
  keypadMode?: 'amount' | 'count';
  keypadTitle?: string;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  min: 0,
  step: 1,
  allowDecimal: false,
  keypadMode: 'amount',
});

const emit = defineEmits<{
  'update:modelValue': [value: number | null];
}>();

const showKeypad = ref(false);

// Format value for display - showing 2 decimals for decimal inputs
const displayValue = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined) return '';
  if (props.allowDecimal) {
    // For decimal inputs, show with 2 decimal places as string
    return props.modelValue.toFixed(2);
  }
  return props.modelValue.toString();
});

// Determine keypad mode based on props
const effectiveKeypadMode = computed(() => {
  // Percentage should use amount mode (no "vouchers" text)
  return props.keypadMode;
});

// Determine keypad title based on context
const effectiveKeypadTitle = computed(() => {
  // Use custom title if provided
  if (props.keypadTitle) return props.keypadTitle;
  
  // Fall back to context-based title
  if (props.suffix === '%') return 'Enter Percentage';
  if (props.prefix === '₱') return 'Enter Amount';
  return 'Enter Value';
});

// Open keypad on click
const handleClick = () => {
  if (props.disabled) return;
  showKeypad.value = true;
};

// Handle keypad confirm
const handleConfirm = (value: number) => {
  emit('update:modelValue', value);
  showKeypad.value = false;
};
</script>

<template>
  <div>
    <div class="relative">
      <!-- Prefix -->
      <span
        v-if="prefix"
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-base md:text-sm z-10"
      >
        {{ prefix }}
      </span>
      
      <!-- Formatted Display Input -->
      <input
        type="text"
        :value="displayValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :class="[
          'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
          'cursor-pointer hover:bg-accent',
          prefix && 'pl-8',
          suffix && 'pr-8',
        ]"
        readonly
        @click="handleClick"
      >
      
      <!-- Suffix -->
      <span
        v-if="suffix"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground text-base md:text-sm z-10"
      >
        {{ suffix }}
      </span>
    </div>
    
    <NumericKeypad
      :open="showKeypad"
      @update:open="(val) => showKeypad = val"
      @confirm="handleConfirm"
      :model-value="modelValue"
      :mode="effectiveKeypadMode"
      :min="min"
      :max="max"
      :allow-decimal="allowDecimal"
      :title="effectiveKeypadTitle"
      :hide-currency="suffix === '%'"
    />
  </div>
</template>
