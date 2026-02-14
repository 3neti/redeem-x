<script setup lang="ts">
import VoucherInstructionsForm from '@/components/voucher/forms/VoucherInstructionsForm.vue';

interface Props {
  instructions: any;
}

const props = defineProps<Props>();
</script>

<template>
  <div class="space-y-4">
    <div v-if="!instructions" class="text-center text-muted-foreground py-8">
      No instructions configured
    </div>
    
    <VoucherInstructionsForm
      v-else
      :model-value="instructions"
      :readonly="true"
      :show-json-preview="false"
      class="space-y-3"
    />
  </div>
</template>
