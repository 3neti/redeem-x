<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { RefreshCw, Clock, TrendingUp, TrendingDown, AlertTriangle } from 'lucide-vue-next'

interface BalanceData {
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
  recorded_at: string
}

const props = withDefaults(defineProps<{
  data?: BalanceData
  trend?: TrendEntry[]
  account?: string
}>(), {
  data: undefined,
  trend: () => [],
  account: ''
})

const refreshing = ref(false)

const formattedBalance = computed(() => {
  if (!props.data) return 'N/A'
  
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: props.data.currency || 'PHP'
  }).format(props.data.balance / 100)
})

const formattedAvailable = computed(() => {
  if (!props.data) return 'N/A'
  
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: props.data.currency || 'PHP'
  }).format(props.data.available_balance / 100)
})

const lastChecked = computed(() => {
  if (!props.data?.checked_at) return 'Never'
  
  const date = new Date(props.data.checked_at)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  const minutes = Math.floor(diff / 60000)
  
  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`
  
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`
  
  return date.toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  })
})

const trendDirection = computed(() => {
  if (!props.trend || props.trend.length < 2) return null
  
  const latest = props.trend[props.trend.length - 1]?.balance
  const previous = props.trend[props.trend.length - 2]?.balance
  
  if (!latest || !previous) return null
  
  return latest > previous ? 'up' : latest < previous ? 'down' : 'flat'
})

const refresh = async () => {
  if (!props.data?.account_number) return
  
  refreshing.value = true
  
  try {
    await fetch(`/api/v1/balances/${props.data.account_number}/refresh`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })
    
    // Reload the page data
    router.reload({ only: ['balance'] })
  } catch (error) {
    console.error('Failed to refresh balance:', error)
  } finally {
    setTimeout(() => {
      refreshing.value = false
    }, 1000)
  }
}

const checkBalanceNow = async () => {
  const accountNumber = props.data?.account_number || props.account
  if (!accountNumber) return
  
  refreshing.value = true
  
  try {
    await fetch(`/api/v1/balances/${accountNumber}/refresh`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })
    
    // Reload the entire page to get all updated data
    router.reload()
  } catch (error) {
    console.error('Failed to check balance:', error)
  } finally {
    setTimeout(() => {
      refreshing.value = false
    }, 1000)
  }
}
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="flex items-center gap-2">
        Account Balance
        <AlertTriangle v-if="data?.is_low" class="h-5 w-5 text-destructive" />
      </CardTitle>
      <CardDescription v-if="data">{{ data.account_number }} ({{ data.gateway }})</CardDescription>
      <CardDescription v-else>No balance data available</CardDescription>
    </CardHeader>
    <CardContent>
      <div v-if="data" class="space-y-4">
        <!-- Current Balance -->
        <div>
          <p class="text-sm text-muted-foreground">Current Balance</p>
          <p class="text-3xl font-bold" :class="{ 'text-destructive': data.is_low }">
            {{ formattedBalance }}
          </p>
        </div>
        
        <!-- Available Balance -->
        <div>
          <p class="text-sm text-muted-foreground">Available Balance</p>
          <p class="text-xl font-semibold">{{ formattedAvailable }}</p>
        </div>
        
        <!-- Last Checked -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
          <Clock class="h-4 w-4" />
          <span>Updated {{ lastChecked }}</span>
        </div>
        
        <!-- Trend Indicator -->
        <div v-if="trendDirection" class="flex items-center gap-2 text-sm">
          <TrendingUp v-if="trendDirection === 'up'" class="h-4 w-4 text-green-600" />
          <TrendingDown v-if="trendDirection === 'down'" class="h-4 w-4 text-red-600" />
          <span class="text-muted-foreground">
            Balance is {{ trendDirection === 'up' ? 'increasing' : 'decreasing' }}
          </span>
        </div>
        
        <!-- Low Balance Warning -->
        <div v-if="data.is_low" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
          <AlertTriangle class="h-4 w-4 inline mr-2" />
          Balance is below threshold
        </div>
        
        <!-- Refresh Button -->
        <Button 
          @click="refresh" 
          :disabled="refreshing" 
          size="sm" 
          variant="outline" 
          class="w-full"
        >
          <RefreshCw :class="{ 'animate-spin': refreshing }" class="mr-2 h-4 w-4" />
          Refresh Balance
        </Button>
      </div>
      
      <!-- No Data State -->
      <div v-else class="py-8 text-center text-muted-foreground space-y-4">
        <div>
          <p class="text-lg font-medium">No balance data available</p>
          <p class="text-sm mt-2">Balance has not been checked yet. Click below to load the latest balance from your bank account.</p>
        </div>
        
        <Button 
          @click="checkBalanceNow" 
          :disabled="refreshing"
          size="default"
          class="mx-auto"
        >
          <RefreshCw :class="{ 'animate-spin': refreshing }" class="mr-2 h-4 w-4" />
          {{ refreshing ? 'Checking Balance...' : 'Check Balance Now' }}
        </Button>
        
        <p class="text-xs">
          Or run: <code class="px-2 py-1 bg-muted rounded">php artisan balances:check --account={{ account }}</code>
        </p>
      </div>
    </CardContent>
  </Card>
</template>
