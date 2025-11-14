<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { AlertTriangle, CheckCircle, AlertCircle, Shield } from 'lucide-vue-next'

interface ReconciliationStatus {
  enabled: boolean
  status: 'safe' | 'warning' | 'critical' | 'disabled'
  message: string
  bank_balance?: number
  system_balance?: number
  discrepancy?: number
  usage_percent?: number
  available?: number
  buffer?: number
  formatted?: {
    bank_balance: string
    system_balance: string
    discrepancy: string
    available: string
    buffer: string
  }
  suppressed?: boolean
}

const props = defineProps<{
  status: ReconciliationStatus
}>()

const statusConfig = computed(() => {
  if (!props.status.enabled) {
    return {
      variant: 'default' as const,
      icon: Shield,
      iconColor: 'text-muted-foreground',
      bgColor: 'bg-muted/50',
      borderColor: 'border-muted',
      title: 'Reconciliation Disabled',
    }
  }

  switch (props.status.status) {
    case 'critical':
      return {
        variant: 'destructive' as const,
        icon: AlertTriangle,
        iconColor: 'text-destructive',
        bgColor: 'bg-destructive/10',
        borderColor: 'border-destructive',
        title: 'CRITICAL: Action Required',
      }
    case 'warning':
      return {
        variant: 'default' as const,
        icon: AlertCircle,
        iconColor: 'text-yellow-600',
        bgColor: 'bg-yellow-50 dark:bg-yellow-950/20',
        borderColor: 'border-yellow-600/50',
        title: 'Warning: Approaching Limit',
      }
    case 'safe':
    default:
      return {
        variant: 'default' as const,
        icon: CheckCircle,
        iconColor: 'text-green-600',
        bgColor: 'bg-green-50 dark:bg-green-950/20',
        borderColor: 'border-green-600/50',
        title: 'Balances Reconciled',
      }
  }
})

const shouldShow = computed(() => {
  if (!props.status.enabled) return false
  if (props.status.suppressed && props.status.status === 'warning') return false
  return props.status.status !== 'safe' || true // Always show for now
})
</script>

<template>
  <Card v-if="shouldShow" :class="[statusConfig.bgColor, statusConfig.borderColor, 'border-2']">
    <CardHeader>
      <CardTitle class="flex items-center gap-2">
        <component :is="statusConfig.icon" :class="[statusConfig.iconColor, 'h-5 w-5']" />
        {{ statusConfig.title }}
      </CardTitle>
      <CardDescription>
        Balance reconciliation status
      </CardDescription>
    </CardHeader>
    <CardContent>
      <div v-if="!status.enabled" class="text-muted-foreground">
        <p>Reconciliation checks are currently disabled.</p>
        <p class="text-sm mt-2">
          Enable in <code class="px-1 py-0.5 bg-muted rounded">.env</code> with:
        </p>
        <code class="block mt-1 px-2 py-1 bg-muted rounded text-xs">
          BALANCE_RECONCILIATION_ENABLED=true
        </code>
      </div>

      <div v-else class="space-y-4">
        <!-- Status Message -->
        <Alert :variant="statusConfig.variant">
          <AlertDescription class="font-medium">
            {{ status.message }}
          </AlertDescription>
        </Alert>

        <!-- Balance Breakdown -->
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-muted-foreground mb-1">Bank Balance</p>
            <p class="text-lg font-semibold">{{ status.formatted?.bank_balance }}</p>
          </div>
          <div>
            <p class="text-muted-foreground mb-1">System Balance</p>
            <p class="text-lg font-semibold">{{ status.formatted?.system_balance }}</p>
          </div>
        </div>

        <!-- Usage Percentage -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-muted-foreground">Usage</span>
            <Badge :variant="statusConfig.variant">
              {{ status.usage_percent }}%
            </Badge>
          </div>
          <div class="w-full bg-muted rounded-full h-2">
            <div 
              class="h-2 rounded-full transition-all"
              :class="{
                'bg-green-600': status.status === 'safe',
                'bg-yellow-600': status.status === 'warning',
                'bg-destructive': status.status === 'critical'
              }"
              :style="{ width: Math.min(status.usage_percent || 0, 100) + '%' }"
            />
          </div>
        </div>

        <!-- Available & Buffer -->
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-muted-foreground mb-1">Available</p>
            <p class="font-semibold">{{ status.formatted?.available }}</p>
          </div>
          <div>
            <p class="text-muted-foreground mb-1">Buffer</p>
            <p class="font-semibold">{{ status.formatted?.buffer }}</p>
          </div>
        </div>

        <!-- Discrepancy (if critical) -->
        <div v-if="status.status === 'critical'" class="rounded-md border border-destructive p-3">
          <p class="text-sm font-medium text-destructive mb-1">
            Discrepancy: {{ status.formatted?.discrepancy }}
          </p>
          <p class="text-xs text-destructive/80">
            System balance exceeds bank balance by this amount. 
            Stop voucher generation immediately and reconcile.
          </p>
        </div>

        <!-- Action Message -->
        <div v-if="status.status === 'critical'" class="text-sm">
          <p class="font-medium mb-1">Action Required:</p>
          <ol class="list-decimal list-inside space-y-1 text-muted-foreground">
            <li>Stop all voucher generation</li>
            <li>Verify bank account balance</li>
            <li>Audit system wallet balances</li>
            <li>Contact administrator if discrepancy persists</li>
          </ol>
        </div>
        
        <div v-else-if="status.status === 'warning'" class="text-sm text-muted-foreground">
          <p>⚠️ System balance is approaching bank balance limit. Consider adding funds to your bank account.</p>
        </div>
        
        <div v-else class="text-sm text-muted-foreground">
          <p>✅ System and bank balances are properly reconciled.</p>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
