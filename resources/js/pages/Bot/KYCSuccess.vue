<script setup lang="ts">
interface Props {
    status: 'approved' | 'rejected' | 'processing' | 'cancelled' | 'error';
    message: string;
}

const props = defineProps<Props>();

// Status icon mapping
const statusIcon = {
    approved: '✅',
    rejected: '❌',
    processing: '⏳',
    cancelled: '🚫',
    error: '⚠️',
};

// Status color mapping
const statusColor = {
    approved: '#22c55e', // green
    rejected: '#ef4444', // red
    processing: '#f59e0b', // amber
    cancelled: '#6b7280', // gray
    error: '#ef4444', // red
};

// Get icon for current status
const icon = statusIcon[props.status] || '📋';
const color = statusColor[props.status] || '#6b7280';

// Try to generate Telegram deep link (may not work in all cases)
function openTelegram() {
    // Try to open Telegram app
    window.location.href = 'tg://';
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8 text-center">
            <!-- Status Icon -->
            <div class="text-6xl mb-4">{{ icon }}</div>
            
            <!-- Title based on status -->
            <h1 class="text-2xl font-bold mb-3" :style="{ color }">
                {{ status === 'approved' ? 'Identity Verified!' : 
                   status === 'rejected' ? 'Verification Failed' :
                   status === 'processing' ? 'Processing...' :
                   status === 'cancelled' ? 'Cancelled' : 'Error' }}
            </h1>
            
            <!-- Message -->
            <p class="text-gray-600 mb-6">{{ message }}</p>
            
            <!-- Instructions -->
            <div class="bg-blue-50 rounded-lg p-4 mb-6">
                <p class="text-blue-800 font-medium mb-2">
                    📱 Return to Telegram
                </p>
                <p class="text-blue-600 text-sm">
                    Go back to your Telegram chat and tap <strong>Continue</strong> to proceed with your redemption.
                </p>
            </div>
            
            <!-- Open Telegram Button -->
            <button
                @click="openTelegram"
                class="w-full py-3 px-4 bg-blue-500 text-white rounded-lg font-medium hover:bg-blue-600 transition-colors"
            >
                Open Telegram
            </button>
            
            <!-- Close Tab Hint -->
            <p class="text-gray-400 text-sm mt-4">
                You can close this tab after returning to Telegram.
            </p>
        </div>
    </div>
</template>
