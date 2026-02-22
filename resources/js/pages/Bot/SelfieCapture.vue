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

const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const stream = ref<MediaStream | null>(null);
const hasCaptured = ref(false);
const capturedImage = ref('');
const uploading = ref(false);
const uploadSuccess = ref(false);
const uploadError = ref<string | null>(null);
const cameraError = ref<string | null>(null);
const showInstructions = ref(false);

// Detect platform
const platform = computed(() => {
    return window.Telegram?.WebApp?.platform || 'unknown';
});

const isIOS = computed(() => {
    return platform.value === 'ios';
});

const isAndroid = computed(() => {
    return platform.value === 'android';
});

const supportsDirectCamera = computed(() => {
    // iOS supports camera capture, Android has bugs
    return isIOS.value;
});

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

async function startCamera() {
    try {
        cameraError.value = null;
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { 
                facingMode: 'user', 
                width: { ideal: 640 }, 
                height: { ideal: 480 } 
            }
        });
        
        if (videoRef.value) {
            videoRef.value.srcObject = stream.value;
        }
    } catch (err: any) {
        console.error('Camera error:', err);
        if (err.name === 'NotAllowedError') {
            cameraError.value = 'Camera access denied. Please allow camera access.';
        } else if (err.name === 'NotFoundError') {
            cameraError.value = 'No camera found on this device.';
        } else {
            cameraError.value = 'Failed to access camera.';
        }
        // Fall back to file picker
        showInstructions.value = true;
    }
}

function stopCamera() {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        stream.value = null;
    }
}

function captureSelfie() {
    if (!videoRef.value || !canvasRef.value) return;
    
    const canvas = canvasRef.value;
    const video = videoRef.value;
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const ctx = canvas.getContext('2d');
    if (ctx) {
        ctx.drawImage(video, 0, 0);
        capturedImage.value = canvas.toDataURL('image/jpeg', 0.8);
        hasCaptured.value = true;
        stopCamera();
    }
}

function handleFileSelect(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            capturedImage.value = e.target?.result as string;
            hasCaptured.value = true;
        };
        reader.readAsDataURL(file);
    }
}

function retakeSelfie() {
    hasCaptured.value = false;
    capturedImage.value = '';
    if (supportsDirectCamera.value && !showInstructions.value) {
        startCamera();
    }
}

function openFilePicker() {
    fileInputRef.value?.click();
}

async function uploadSelfie() {
    if (!capturedImage.value) return;
    
    uploading.value = true;
    uploadError.value = null;
    
    // Show progress on Telegram main button
    window.Telegram?.WebApp?.MainButton?.showProgress(true);
    
    try {
        const response = await fetch(props.uploadUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                chat_id: props.chatId,
                selfie_base64: capturedImage.value,
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
        uploadError.value = err.message || 'Failed to upload selfie. Please try again.';
        window.Telegram?.WebApp?.MainButton?.hideProgress();
    } finally {
        uploading.value = false;
    }
}

function completeAndClose() {
    // Try to send data to bot (may not work depending on how Mini App was opened)
    // Then close the Mini App regardless
    try {
        window.Telegram?.WebApp?.sendData('selfie_uploaded');
    } catch (e) {
        // sendData might fail, but we still want to close
        console.log('sendData failed:', e);
    }
    // Ensure the Mini App closes (sendData should auto-close, but add fallback)
    setTimeout(() => {
        window.Telegram?.WebApp?.close();
    }, 100);
}

function exitWithoutPhoto() {
    // Close without uploading - user will need to send photo manually
    window.Telegram?.WebApp?.close();
}

// Initialize
onMounted(() => {
    // Signal to Telegram that the Mini App is ready
    window.Telegram?.WebApp?.ready();
    window.Telegram?.WebApp?.expand();
    
    // On iOS, try direct camera
    if (supportsDirectCamera.value) {
        startCamera();
    } else {
        // On Android/other, show instructions
        showInstructions.value = true;
    }
    
    // Setup main button for upload
    if (window.Telegram?.WebApp?.MainButton) {
        window.Telegram.WebApp.MainButton.text = 'Upload Selfie';
        window.Telegram.WebApp.MainButton.onClick(uploadSelfie);
    }
    
    // Setup back button
    if (window.Telegram?.WebApp?.BackButton) {
        window.Telegram.WebApp.BackButton.show();
        window.Telegram.WebApp.BackButton.onClick(exitWithoutPhoto);
    }
});

onUnmounted(() => {
    stopCamera();
    window.Telegram?.WebApp?.MainButton?.offClick(uploadSelfie);
    window.Telegram?.WebApp?.BackButton?.offClick(exitWithoutPhoto);
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
                <h1 class="text-xl font-semibold mb-2">Selfie Uploaded!</h1>
                <p class="text-lg mt-6" :style="{ color: themeColors.textColor }">
                    Tap <strong>Close</strong> (top-left) to continue with your redemption.
                </p>
            </div>

            <!-- Normal Flow (when not success) -->
            <template v-else>
                <!-- Header -->
                <div class="text-center mb-6">
                    <h1 class="text-xl font-semibold mb-2">📸 Take a Selfie</h1>
                    <p :style="{ color: themeColors.hintColor }">
                        {{ supportsDirectCamera && !showInstructions ? 'Position your face and tap capture' : 'Select or take a photo of yourself' }}
                    </p>
                </div>

                <!-- Error Alert -->
            <div 
                v-if="uploadError || cameraError" 
                class="mb-4 p-3 rounded-lg"
                :style="{ backgroundColor: '#fee2e2', color: '#dc2626' }"
            >
                {{ uploadError || cameraError }}
            </div>

            <!-- iOS: Direct Camera Capture -->
            <div v-if="supportsDirectCamera && !showInstructions">
                <!-- Live Camera Feed -->
                <div v-if="!hasCaptured" class="relative rounded-lg overflow-hidden bg-black mb-4">
                    <video
                        ref="videoRef"
                        autoplay
                        playsinline
                        muted
                        class="w-full h-80 object-cover"
                    ></video>
                    <!-- Face guide overlay -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-48 h-64 border-2 border-white/50 rounded-full"></div>
                    </div>
                </div>

                <!-- Captured Preview -->
                <div v-else class="rounded-lg overflow-hidden mb-4">
                    <img :src="capturedImage" alt="Your selfie" class="w-full h-80 object-cover" />
                </div>

                <!-- Capture/Retake Buttons -->
                <div class="flex gap-3">
                    <button
                        v-if="!hasCaptured"
                        @click="captureSelfie"
                        class="flex-1 py-3 px-4 rounded-lg font-medium"
                        :style="{ backgroundColor: themeColors.buttonColor, color: themeColors.buttonTextColor }"
                        :disabled="!!cameraError"
                    >
                        📷 Capture
                    </button>
                    <template v-else>
                        <button
                            @click="retakeSelfie"
                            class="flex-1 py-3 px-4 rounded-lg font-medium"
                            :style="{ backgroundColor: themeColors.secondaryBgColor, color: themeColors.textColor }"
                            :disabled="uploading"
                        >
                            🔄 Retake
                        </button>
                        <button
                            @click="uploadSelfie"
                            class="flex-1 py-3 px-4 rounded-lg font-medium"
                            :style="{ backgroundColor: themeColors.buttonColor, color: themeColors.buttonTextColor }"
                            :disabled="uploading"
                        >
                            {{ uploading ? 'Uploading...' : '✓ Use Photo' }}
                        </button>
                    </template>
                </div>
            </div>

            <!-- Android/Other: Instructions + File Picker -->
            <div v-else>
                <!-- Instructions Card -->
                <div 
                    class="rounded-lg p-4 mb-4"
                    :style="{ backgroundColor: themeColors.secondaryBgColor }"
                >
                    <p class="mb-3">📱 <strong>To take a selfie:</strong></p>
                    <ol class="list-decimal list-inside space-y-2 text-sm" :style="{ color: themeColors.hintColor }">
                        <li>Tap the button below</li>
                        <li>Select "Camera" to take a new photo</li>
                        <li>Or choose an existing photo from gallery</li>
                    </ol>
                </div>

                <!-- Preview if captured -->
                <div v-if="hasCaptured" class="rounded-lg overflow-hidden mb-4">
                    <img :src="capturedImage" alt="Your selfie" class="w-full h-64 object-cover" />
                </div>

                <!-- File Input (hidden) -->
                <input
                    ref="fileInputRef"
                    type="file"
                    accept="image/*"
                    capture="user"
                    @change="handleFileSelect"
                    class="hidden"
                />

                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button
                        v-if="!hasCaptured"
                        @click="openFilePicker"
                        class="flex-1 py-3 px-4 rounded-lg font-medium"
                        :style="{ backgroundColor: themeColors.buttonColor, color: themeColors.buttonTextColor }"
                    >
                        📸 Select Photo
                    </button>
                    <template v-else>
                        <button
                            @click="retakeSelfie"
                            class="flex-1 py-3 px-4 rounded-lg font-medium"
                            :style="{ backgroundColor: themeColors.secondaryBgColor, color: themeColors.textColor }"
                            :disabled="uploading"
                        >
                            🔄 Change
                        </button>
                        <button
                            @click="uploadSelfie"
                            class="flex-1 py-3 px-4 rounded-lg font-medium"
                            :style="{ backgroundColor: themeColors.buttonColor, color: themeColors.buttonTextColor }"
                            :disabled="uploading"
                        >
                            {{ uploading ? 'Uploading...' : '✓ Use Photo' }}
                        </button>
                    </template>
                </div>

                <!-- Exit Link -->
                <div class="text-center mt-4">
                    <button
                        @click="exitWithoutPhoto"
                        class="text-sm underline"
                        :style="{ color: themeColors.hintColor }"
                    >
                        Skip (send photo manually in chat)
                    </button>
                </div>
            </div>

                <!-- Hidden canvas for capture -->
                <canvas ref="canvasRef" class="hidden"></canvas>
            </template>
        </div>
    </div>
</template>
