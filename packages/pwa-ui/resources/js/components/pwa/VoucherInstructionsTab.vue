<script setup lang="ts">
import VoucherInstructionsForm from '@/components/voucher/forms/VoucherInstructionsForm.vue';
import { computed } from 'vue';

interface Props {
  instructions: any;
}

const props = defineProps<Props>();

// Ensure instructions have the required structure with defaults
const safeInstructions = computed(() => {
  if (!props.instructions) return null;
  
  return {
    ...props.instructions,
    selectedInputFields: props.instructions.selectedInputFields || [],
  };
});

// Empty input field options for readonly mode
const inputFieldOptions = [];
</script>

<template>
  <div class="space-y-4">
    <div v-if="!safeInstructions" class="text-center text-muted-foreground py-8">
      No instructions configured
    </div>
    
    <VoucherInstructionsForm
      v-else
      :model-value="safeInstructions"
      :input-field-options="inputFieldOptions"
      :readonly="true"
      :show-json-preview="false"
      class="space-y-3"
    />
  </div>
</template>
