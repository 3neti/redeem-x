<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { DollarSign, FileText, Bell, MessageSquare } from 'lucide-vue-next';

interface Props {
  instructions: any;
}

const props = defineProps<Props>();

// Format currency
const formatCurrency = (amount: number | null | undefined) => {
  if (!amount) return '₱0.00';
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};
</script>

<template>
  <div class="space-y-4">
    <div v-if="!instructions" class="text-center text-muted-foreground py-8">
      No instructions configured
    </div>
    
    <template v-else>
      <!-- Cash Amount -->
      <Card v-if="instructions.cash">
        <CardHeader>
          <div class="flex items-center gap-2">
            <DollarSign class="h-5 w-5" />
            <CardTitle class="text-lg">Amount</CardTitle>
          </div>
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ formatCurrency(instructions.cash.amount) }}</div>
          <div v-if="instructions.cash.currency" class="text-sm text-muted-foreground mt-1">
            Currency: {{ instructions.cash.currency }}
          </div>
        </CardContent>
      </Card>

      <!-- Input Fields -->
      <Card v-if="instructions.inputs?.fields?.length > 0">
        <CardHeader>
          <div class="flex items-center gap-2">
            <FileText class="h-5 w-5" />
            <CardTitle class="text-lg">Required Inputs</CardTitle>
          </div>
        </CardHeader>
        <CardContent>
          <div class="flex flex-wrap gap-2">
            <Badge v-for="field in instructions.inputs.fields" :key="field" variant="secondary">
              {{ field }}
            </Badge>
          </div>
        </CardContent>
      </Card>

      <!-- Feedback Channels -->
      <Card v-if="instructions.feedback?.email || instructions.feedback?.mobile || instructions.feedback?.webhook">
        <CardHeader>
          <div class="flex items-center gap-2">
            <Bell class="h-5 w-5" />
            <CardTitle class="text-lg">Feedback Channels</CardTitle>
          </div>
        </CardHeader>
        <CardContent class="space-y-2">
          <div v-if="instructions.feedback.email" class="text-sm">
            <span class="text-muted-foreground">Email:</span> {{ instructions.feedback.email }}
          </div>
          <div v-if="instructions.feedback.mobile" class="text-sm">
            <span class="text-muted-foreground">Mobile:</span> {{ instructions.feedback.mobile }}
          </div>
          <div v-if="instructions.feedback.webhook" class="text-sm">
            <span class="text-muted-foreground">Webhook:</span> {{ instructions.feedback.webhook }}
          </div>
        </CardContent>
      </Card>

      <!-- Rider Message -->
      <Card v-if="instructions.rider?.message">
        <CardHeader>
          <div class="flex items-center gap-2">
            <MessageSquare class="h-5 w-5" />
            <CardTitle class="text-lg">Rider Message</CardTitle>
          </div>
        </CardHeader>
        <CardContent>
          <p class="text-sm">{{ instructions.rider.message }}</p>
          <div v-if="instructions.rider.url" class="mt-2 text-sm">
            <span class="text-muted-foreground">URL:</span> 
            <a :href="instructions.rider.url" target="_blank" class="text-primary hover:underline">
              {{ instructions.rider.url }}
            </a>
          </div>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
