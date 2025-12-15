<script setup lang="ts">
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  flow_id: string
  step_index: number
  handler_name: string
  handler_title: string
  install_hint: string
  is_production: boolean
  can_skip: boolean
}>()

const handleSkip = () => {
  console.log('[MissingHandler] Skipping step', {
    flow_id: props.flow_id,
    step_index: props.step_index,
    handler_name: props.handler_name,
  })
  
  // Submit minimal data to skip this step (must have at least one field)
  router.post(`/form-flow/${props.flow_id}/step/${props.step_index}`, {
    data: {
      _skipped: true,
    }
  }, {
    onSuccess: () => {
      console.log('[MissingHandler] Skip successful')
    },
    onError: (errors) => {
      console.error('[MissingHandler] Skip failed', errors)
      alert('Error: ' + JSON.stringify(errors))
    },
  })
}

const handleGoBack = () => {
  router.visit('/disburse')
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
    <div class="max-w-md w-full">
      <!-- Production Mode: Error -->
      <div v-if="is_production" class="bg-white rounded-lg shadow-lg p-8 text-center">
        <div class="mb-6">
          <div class="mx-auto w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center">
            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-4">
          Step Unavailable
        </h1>

        <p class="text-gray-600 mb-6">
          This redemption requires <strong>{{ handler_title }}</strong>, but the system is temporarily unavailable.
        </p>

        <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-left">
          <p class="text-gray-500 mb-2">Reference Information:</p>
          <p class="font-mono text-xs text-gray-700">
            HANDLER-{{ handler_name.toUpperCase() }}-MISSING
          </p>
        </div>

        <button
          @click="handleGoBack"
          class="w-full bg-gray-900 text-white px-6 py-3 rounded-lg font-medium hover:bg-gray-800 transition-colors"
        >
          Go Back
        </button>

        <p class="mt-4 text-sm text-gray-500">
          Please contact support if this issue persists
        </p>
      </div>

      <!-- Development Mode: Warning with Skip -->
      <div v-else class="bg-white rounded-lg shadow-lg p-8">
        <div class="mb-6">
          <div class="mx-auto w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center">
            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2 text-center">
          Handler Missing: {{ handler_name }}
        </h1>

        <p class="text-gray-600 mb-6 text-center">
          The '<strong>{{ handler_name }}</strong>' handler is not installed.
        </p>

        <div class="bg-gray-50 rounded-lg p-4 mb-6">
          <p class="text-sm font-medium text-gray-700 mb-2">Install command:</p>
          <div class="bg-gray-900 text-green-400 px-4 py-3 rounded font-mono text-sm overflow-x-auto">
            {{ install_hint }}
          </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <p class="text-sm text-blue-800">
            <strong>Development Mode:</strong> This step has been automatically skipped.
          </p>
        </div>

        <div class="flex gap-3">
          <button
            @click="handleGoBack"
            class="flex-1 bg-gray-200 text-gray-900 px-6 py-3 rounded-lg font-medium hover:bg-gray-300 transition-colors"
          >
            Go Back
          </button>
          <button
            v-if="can_skip"
            @click="handleSkip"
            class="flex-1 bg-orange-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-orange-700 transition-colors"
          >
            Continue Anyway
          </button>
        </div>

        <p class="mt-4 text-xs text-gray-500 text-center">
          Step: {{ handler_title }} | Handler: {{ handler_name }}
        </p>
      </div>
    </div>
  </div>
</template>
