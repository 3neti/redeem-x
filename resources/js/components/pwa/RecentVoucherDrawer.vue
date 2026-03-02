<script setup lang="ts">
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { QrCode } from 'lucide-vue-next';
import type { KioskHistoryEntry } from '@/composables/useKioskHistory';

interface Props {
  open: boolean;
  vouchers: KioskHistoryEntry[];
  getRelativeTime: (timestamp: string) => string;
}

defineProps<Props>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  'show-qr': [voucher: KioskHistoryEntry];
}>();
</script>

<template>
  <Sheet :open="open" @update:open="emit('update:open', $event)">
    <SheetContent side="bottom" class="h-[70vh] overflow-hidden flex flex-col">
      <SheetHeader>
        <SheetTitle class="text-xl">Recent Vouchers</SheetTitle>
        <p class="text-sm text-muted-foreground">
          Last 24 hours
        </p>
      </SheetHeader>

      <!-- Voucher List -->
      <div class="flex-1 overflow-y-auto mt-6 -mx-6 px-6 space-y-3">
        <div
          v-for="voucher in vouchers"
          :key="voucher.code"
          class="
            bg-card border rounded-lg p-4
            hover:border-primary/50
            transition-colors duration-200
          "
        >
          <!-- Code & Time -->
          <div class="flex items-start justify-between mb-3">
            <div>
              <p class="text-xs text-muted-foreground mb-1">Voucher Code</p>
              <p class="text-lg font-mono font-bold tracking-wider">
                {{ voucher.code }}
              </p>
            </div>
            <div class="text-right">
              <p class="text-xs text-muted-foreground">
                {{ getRelativeTime(voucher.issued_at) }}
              </p>
            </div>
          </div>

          <!-- Amount & Action -->
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-muted-foreground">Amount</p>
              <p class="text-xl font-bold">
                {{ voucher.formatted_amount }}
              </p>
            </div>
            <Button
              size="sm"
              @click="emit('show-qr', voucher)"
              class="gap-2"
            >
              <QrCode class="h-4 w-4" />
              Show QR
            </Button>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="vouchers.length === 0"
          class="py-12 text-center"
        >
          <p class="text-muted-foreground">
            No recent vouchers
          </p>
        </div>
      </div>
    </SheetContent>
  </Sheet>
</template>
