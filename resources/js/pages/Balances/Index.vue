<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import BalanceWidget from '@/components/BalanceWidget.vue'
import ReconciliationStatusCard from '@/components/ReconciliationStatusCard.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { History, Bell } from 'lucide-vue-next'

interface BalanceData {
  id: number
  account_number: string
  gateway: string
  balance: number
  available_balance: number
  currency: string
  checked_at: string
  is_low?: boolean
}

interface TrendEntry {
  balance: number
  available_balance: number
  currency: string
  formatted_balance: string
  formatted_available_balance: string
  recorded_at: string
}

interface BalanceAlert {
  id: number
  account_number: string
  gateway: string
  threshold: number
  alert_type: string
  recipients: string[]
  enabled: boolean
  last_triggered_at: string | null
}

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
  balance: BalanceData | null
  trend: TrendEntry[]
  history: TrendEntry[]
  alerts: BalanceAlert[]
  accountNumber: string
  canManageAlerts: boolean
  reconciliation: ReconciliationStatus
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Balance Monitoring',
    href: '/balances',
  },
]

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  })
}

const getAlertBadgeVariant = (enabled: boolean) => {
  return enabled ? 'default' : 'secondary'
}
</script>

<template>
  <Head title="Balance Monitoring" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
      <!-- Page Header -->
      <div>
        <h1 class="text-3xl font-bold tracking-tight">Balance Monitoring</h1>
        <p class="text-muted-foreground mt-1">
          Account: <span class="font-mono">{{ accountNumber }}</span>
        </p>
      </div>

      <!-- Reconciliation Status Card -->
      <ReconciliationStatusCard :status="reconciliation" />

      <!-- Balance Widget -->
      <div class="grid gap-6 md:grid-cols-3">
        <div class="md:col-span-1">
          <BalanceWidget :data="balance" :trend="trend" />
        </div>

        <!-- Active Alerts Card -->
        <Card class="md:col-span-2">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Bell class="h-5 w-5" />
              Active Alerts
            </CardTitle>
            <CardDescription>
              {{ alerts.length }} alert{{ alerts.length !== 1 ? 's' : '' }} configured for this account
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="alerts.length > 0" class="space-y-3">
              <div
                v-for="alert in alerts"
                :key="alert.id"
                class="flex items-center justify-between rounded-lg border p-3"
              >
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <Badge :variant="getAlertBadgeVariant(alert.enabled)">
                      {{ alert.enabled ? 'Active' : 'Disabled' }}
                    </Badge>
                    <span class="text-sm font-medium">
                      {{ alert.alert_type.toUpperCase() }}
                    </span>
                  </div>
                  <p class="text-sm text-muted-foreground">
                    Threshold: 
                    <span class="font-mono">
                      {{ new Intl.NumberFormat('en-PH', { style: 'currency', currency: balance?.currency || 'PHP' }).format(alert.threshold / 100) }}
                    </span>
                  </p>
                  <p class="text-xs text-muted-foreground">
                    Recipients: {{ alert.recipients.join(', ') }}
                  </p>
                  <p v-if="alert.last_triggered_at" class="text-xs text-muted-foreground mt-1">
                    Last triggered: {{ formatDate(alert.last_triggered_at) }}
                  </p>
                </div>
              </div>
            </div>
            <div v-else class="py-8 text-center text-muted-foreground">
              <Bell class="h-12 w-12 mx-auto mb-3 opacity-50" />
              <p>No alerts configured</p>
              <p class="text-sm mt-1">
                Use <code class="px-2 py-1 bg-muted rounded">php artisan tinker</code> to create alerts
              </p>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Balance History Card -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <History class="h-5 w-5" />
            Recent Balance History
          </CardTitle>
          <CardDescription>
            Last {{ history.length }} balance check{{ history.length !== 1 ? 's' : '' }}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="history.length > 0" class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date & Time</TableHead>
                  <TableHead class="text-right">Balance</TableHead>
                  <TableHead class="text-right">Available</TableHead>
                  <TableHead class="text-right">Change</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="(entry, index) in history" :key="index">
                  <TableCell class="font-mono text-sm">
                    {{ formatDate(entry.recorded_at) }}
                  </TableCell>
                  <TableCell class="text-right font-medium">
                    {{ entry.formatted_balance }}
                  </TableCell>
                  <TableCell class="text-right">
                    {{ entry.formatted_available_balance }}
                  </TableCell>
                  <TableCell class="text-right">
                    <span
                      v-if="index < history.length - 1"
                      :class="{
                        'text-green-600': entry.balance > history[index + 1].balance,
                        'text-red-600': entry.balance < history[index + 1].balance,
                        'text-muted-foreground': entry.balance === history[index + 1].balance
                      }"
                    >
                      {{ entry.balance > history[index + 1].balance ? '↑' : entry.balance < history[index + 1].balance ? '↓' : '−' }}
                      {{
                        new Intl.NumberFormat('en-PH', { 
                          style: 'currency', 
                          currency: entry.currency 
                        }).format(Math.abs(entry.balance - history[index + 1].balance) / 100)
                      }}
                    </span>
                    <span v-else class="text-muted-foreground">−</span>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
          <div v-else class="py-8 text-center text-muted-foreground">
            <History class="h-12 w-12 mx-auto mb-3 opacity-50" />
            <p>No balance history available</p>
            <p class="text-sm mt-1">
              Run <code class="px-2 py-1 bg-muted rounded">php artisan balances:check --account={{ accountNumber }}</code> to start tracking
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
