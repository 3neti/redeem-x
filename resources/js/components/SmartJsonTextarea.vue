<script setup lang="ts">
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  useSmartJsonFormat,
  type SmartJsonFormatOptions,
} from '@/composables/useSmartJsonFormat';

interface Props {
  modelValue: string;
  label?: string;
  placeholder?: string;
  helpText?: string;
  rows?: number;
  formatOptions?: SmartJsonFormatOptions;
}

const props = withDefaults(defineProps<Props>(), {
  label: 'External Metadata',
  placeholder: 'Enter reference code or JSON like {"reference":"REF-001"}',
  helpText: 'Enter a reference code (auto-formatted to JSON) or write custom JSON',
  rows: 4,
  formatOptions: () => ({}),
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const { autoFormat } = useSmartJsonFormat(props.formatOptions);

const localValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const isValid = computed(() => {
  const json = localValue.value.trim();
  if (!json) return true;
  try {
    JSON.parse(json);
    return true;
  } catch {
    return false;
  }
});

const handleBlur = () => {
  const formatted = autoFormat(localValue.value);
  if (formatted !== localValue.value) {
    localValue.value = formatted;
  }
};
</script>

<template>
  <div class="space-y-2">
    <Label for="smart-json-textarea">{{ label }}</Label>
    <Textarea
      id="smart-json-textarea"
      v-model="localValue"
      @blur="handleBlur"
      :placeholder="placeholder"
      :rows="rows"
      class="font-mono text-sm"
    />
    <p v-if="!isValid" class="text-xs text-destructive">
      ⚠ Invalid JSON format
    </p>
    <p v-else class="text-xs text-muted-foreground">
      {{ helpText }}
    </p>
  </div>
</template>
