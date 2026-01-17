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

// Determine keypad mode based on props
const effectiveKeypadMode = computed(() => {
  // If suffix is %, it's a percentage input
  if (props.suffix === '%') return 'count';
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
    <NumberInput
      :model-value="modelValue"
      :min="min"
      :max="max"
      :step="step"
      :placeholder="placeholder"
      :disabled="disabled"
      :prefix="prefix"
      :suffix="suffix"
      :class="{ 'cursor-pointer': !disabled }"
      readonly
      @click="handleClick"
    />
    
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
    />
  </div>
</template>
