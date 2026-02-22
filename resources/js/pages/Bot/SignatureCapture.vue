<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

interface Props {
    chatId: string;
    uploadUrl: string;
}

const props = defineProps<Props>();

// Telegram WebApp API (will be available when running inside Telegram)
declare global {
    interface Window {
        Telegram?: {
            WebApp: {
                platform: string;
                initData: string;
                initDataUnsafe: { user?: { id: number } };
                themeParams: {
                    bg_color?: string;
                    text_color?: string;
                    hint_color?: string;
                    link_color?: string;
                    button_color?: string;
                    button_text_color?: string;
                    secondary_bg_color?: string;
                };
                ready: () => void;
                close: () => void;
                expand: () => void;
                sendData: (data: string) => void;
                MainButton: {
                    text: string;
                    color: string;
                    textColor: string;
                    isVisible: boolean;
                    isActive: boolean;
                    show: () => void;
                    hide: () => void;
                    enable: () => void;
                    disable: () => void;
                    showProgress: (leaveActive?: boolean) => void;
                    hideProgress: () => void;
                    onClick: (callback: () => void) => void;
                    offClick: (callback: () => void) => void;
                };
                BackButton: {
                    isVisible: boolean;
                    show: () => void;
                    hide: () => void;
                    onClick: (callback: () => void) => void;
                    offClick: (callback: () => void) => void;
                };
            };
        };
    }
}

const canvasRef = ref<HTMLCanvasElement | null>(null);
const isDrawing = ref(false);
const hasSignature = ref(false);
const signatureData = ref('');
const uploading = ref(false);
const uploadSuccess = ref(false);
const uploadError = ref<string | null>(null);

let ctx: CanvasRenderingContext2D | null = null;

// Theme colors from Telegram
const themeColors = computed(() => {
    const theme = window.Telegram?.WebApp?.themeParams || {};
    return {
        bgColor: theme.bg_color || '#ffffff',
        textColor: theme.text_color || '#000000',
        hintColor: theme.hint_color || '#999999',
        buttonColor: theme.button_color || '#3390ec',
        buttonTextColor: theme.button_text_color || '#ffffff',
        secondaryBgColor: theme.secondary_bg_color || '#f0f0f0',
    };
});

// Canvas configuration
const config = {
    width: 400,
    height: 200,
    lineWidth: 2,
    lineColor: '#000000',
    lineCap: 'round' as const,
    lineJoin: 'round' as const,
    format: 'image/png',
    quality: 0.9,
};

function initCanvas() {
    if (!canvasRef.value) return;

    ctx = canvasRef.value.getContext('2d');
    if (!ctx) return;

    // Set canvas size with device pixel ratio for high-DPI displays
    const rect = canvasRef.value.getBoundingClientRect();
    canvasRef.value.width = rect.width * window.devicePixelRatio;
    canvasRef.value.height = rect.height * window.devicePixelRatio;
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

    // Set drawing style
    ctx.strokeStyle = config.lineColor;
    ctx.lineWidth = config.lineWidth;
    ctx.lineCap = config.lineCap;
    ctx.lineJoin = config.lineJoin;

    // Fill with white background for PNG
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, rect.width, rect.height);
}

function startDrawing(e: MouseEvent | TouchEvent) {
    if (!ctx || !canvasRef.value) return;
    isDrawing.value = true;

    const rect = canvasRef.value.getBoundingClientRect();
    const x = ('touches' in e ? e.touches[0].clientX : e.clientX) - rect.left;
    const y = ('touches' in e ? e.touches[0].clientY : e.clientY) - rect.top;

    ctx.beginPath();
    ctx.moveTo(x, y);
}

function draw(e: MouseEvent | TouchEvent) {
    if (!isDrawing.value || !ctx || !canvasRef.value) return;
    e.preventDefault();

    hasSignature.value = true;

    const rect = canvasRef.value.getBoundingClientRect();
    const x = ('touches' in e ? e.touches[0].clientX : e.clientX) - rect.left;
    const y = ('touches' in e ? e.touches[0].clientY : e.clientY) - rect.top;

    ctx.lineTo(x, y);
    ctx.stroke();
}

function stopDrawing() {
    isDrawing.value = false;
}

function clearSignature() {
    if (!ctx || !canvasRef.value) return;
    
    const rect = canvasRef.value.getBoundingClientRect();
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, rect.width, rect.height);
    
    // Reset stroke style after fill
    ctx.strokeStyle = config.lineColor;
    
    hasSignature.value = false;
    signatureData.value = '';
}

async function uploadSignature() {
    if (!canvasRef.value || !hasSignature.value) return;

    uploading.value = true;
    uploadError.value = null;

    // Show progress on Telegram main button
    window.Telegram?.WebApp?.MainButton?.showProgress(true);

    try {
        // Convert canvas to base64 data URL
        signatureData.value = canvasRef.value.toDataURL(config.format, config.quality);

        const response = await fetch(props.uploadUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                chat_id: props.chatId,
                signature_base64: signatureData.value,
            }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message || 'Upload failed');
        }

        // Success - show success state
        uploadSuccess.value = true;
        window.Telegram?.WebApp?.MainButton?.hideProgress();

    } catch (err: any) {
        console.error('Upload error:', err);
        uploadError.value = err.message || 'Failed to upload signature. Please try again.';
        window.Telegram?.WebApp?.MainButton?.hideProgress();
    } finally {
        uploading.value = false;
    }
}

function completeAndClose() {
    // Try to send data to bot (may not work depending on how Mini App was opened)
    // Then close the Mini App regardless
    try {
        window.Telegram?.WebApp?.sendData('signature_uploaded');
    } catch (e) {
        // sendData might fail, but we still want to close
        console.log('sendData failed:', e);
    }
    // Ensure the Mini App closes (sendData should auto-close, but add fallback)
    setTimeout(() => {
        window.Telegram?.WebApp?.close();
    }, 100);
}

function exitWithoutSignature() {
    // Close without uploading
    window.Telegram?.WebApp?.close();
}

// Initialize
onMounted(() => {
    // Signal to Telegram that the Mini App is ready
    window.Telegram?.WebApp?.ready();
    window.Telegram?.WebApp?.expand();

    // Initialize canvas
    setTimeout(initCanvas, 100);

    // Setup main button for upload
    if (window.Telegram?.WebApp?.MainButton) {
        window.Telegram.WebApp.MainButton.text = 'Upload Signature';
        window.Telegram.WebApp.MainButton.onClick(uploadSignature);
    }

    // Setup back button
    if (window.Telegram?.WebApp?.BackButton) {
        window.Telegram.WebApp.BackButton.show();
        window.Telegram.WebApp.BackButton.onClick(exitWithoutSignature);
    }
});

onUnmounted(() => {
    window.Telegram?.WebApp?.MainButton?.offClick(uploadSignature);
    window.Telegram?.WebApp?.BackButton?.offClick(exitWithoutSignature);
});
</script>

<template>
    <div 
        class="min-h-screen p-4"
        :style="{ backgroundColor: themeColors.bgColor, color: themeColors.textColor }"
    >
        <div class="max-w-md mx-auto">
            <!-- Success State -->
            <div v-if="uploadSuccess" class="text-center py-8">
                <div class="text-6xl mb-4">✅</div>
                <h1 class="text-xl font-semibold mb-2">Signature Uploaded!</h1>
                <p class="text-lg mt-6" :style="{ color: themeColors.textColor }">
                    Tap <strong>Close</strong> (top-left) to continue with your redemption.
                </p>
            </div>

            <!-- Normal Flow (when not success) -->
            <template v-else>
                <!-- Header -->
                <div class="text-center mb-6">
                    <h1 class="text-xl font-semibold mb-2">✍️ Sign Here</h1>
                    <p :style="{ color: themeColors.hintColor }">
                        Draw your signature in the box below
                    </p>
                </div>

                <!-- Error Alert -->
                <div 
                    v-if="uploadError" 
                    class="mb-4 p-3 rounded-lg"
                    :style="{ backgroundColor: '#fee2e2', color: '#dc2626' }"
                >
                    {{ uploadError }}
                </div>

                <!-- Signature Canvas -->
                <div class="mb-4">
                    <div 
                        class="relative rounded-lg overflow-hidden border-2 border-dashed"
                        :style="{ borderColor: themeColors.hintColor }"
                    >
                        <canvas
                            ref="canvasRef"
                            class="w-full touch-none cursor-crosshair bg-white"
                            :style="{ height: config.height + 'px' }"
                            @mousedown="startDrawing"
                            @mousemove="draw"
                            @mouseup="stopDrawing"
                            @mouseleave="stopDrawing"
                            @touchstart.prevent="startDrawing"
                            @touchmove.prevent="draw"
                            @touchend="stopDrawing"
                        />
                        <!-- Placeholder text -->
                        <div
                            v-if="!hasSignature"
                            class="absolute inset-0 flex items-center justify-center pointer-events-none"
                            :style="{ color: themeColors.hintColor }"
                        >
                            Sign here
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="clearSignature"
                        class="flex-1 py-3 px-4 rounded-lg font-medium"
                        :style="{ backgroundColor: themeColors.secondaryBgColor, color: themeColors.textColor }"
                        :disabled="!hasSignature || uploading"
                    >
                        🔄 Clear
                    </button>
                    <button
                        @click="uploadSignature"
                        class="flex-1 py-3 px-4 rounded-lg font-medium"
                        :style="{ backgroundColor: themeColors.buttonColor, color: themeColors.buttonTextColor }"
                        :disabled="!hasSignature || uploading"
                    >
                        {{ uploading ? 'Uploading...' : '✓ Use Signature' }}
                    </button>
                </div>

                <!-- Exit Link -->
                <div class="text-center mt-4">
                    <button
                        @click="exitWithoutSignature"
                        class="text-sm underline"
                        :style="{ color: themeColors.hintColor }"
                    >
                        Cancel
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
