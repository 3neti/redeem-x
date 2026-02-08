<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { AlertCircle, Link2Off, Clock, ShieldX, FileX } from 'lucide-vue-next'

interface Props {
    error: string
    code: string
}

defineProps<Props>()

const errorIcons: Record<string, any> = {
    VOUCHER_NOT_FOUND: FileX,
    NO_ENVELOPE: FileX,
    INVALID_TOKEN: Link2Off,
    TOKEN_MISMATCH: ShieldX,
    TOKEN_EXPIRED: Clock,
    TOKEN_REVOKED: ShieldX,
}

const errorColors: Record<string, string> = {
    VOUCHER_NOT_FOUND: 'text-orange-500',
    NO_ENVELOPE: 'text-orange-500',
    INVALID_TOKEN: 'text-red-500',
    TOKEN_MISMATCH: 'text-red-500',
    TOKEN_EXPIRED: 'text-yellow-600',
    TOKEN_REVOKED: 'text-red-500',
}
</script>

<template>
    <Head title="Contribution Error" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="mb-4 flex justify-center">
                    <component 
                        :is="errorIcons[code] || AlertCircle" 
                        :class="['h-16 w-16', errorColors[code] || 'text-gray-500']" 
                    />
                </div>
                
                <h1 class="text-xl font-semibold text-gray-900 mb-2">
                    Unable to Access
                </h1>
                
                <p class="text-gray-600 mb-6">
                    {{ error }}
                </p>
                
                <div class="text-xs text-gray-400">
                    Error code: {{ code }}
                </div>
                
                <div v-if="code === 'TOKEN_EXPIRED'" class="mt-6 p-4 bg-yellow-50 rounded-lg text-left">
                    <p class="text-sm text-yellow-800">
                        <strong>Link expired?</strong> Contact the voucher owner to request a new contribution link.
                    </p>
                </div>
                
                <div v-if="code === 'TOKEN_REVOKED'" class="mt-6 p-4 bg-red-50 rounded-lg text-left">
                    <p class="text-sm text-red-800">
                        <strong>Link revoked.</strong> The voucher owner has disabled this contribution link.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
