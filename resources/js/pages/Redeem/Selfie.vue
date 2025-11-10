<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useRedemptionApi } from '@/composables/useRedemptionApi';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertCircle, Camera, RotateCw } from 'lucide-vue-next';

interface ImageConfig {
    width: number;
    height: number;
    quality: number;
    format: string;
}

interface Props {
    voucher_code: string;
    image_config: ImageConfig;
}

const props = defineProps<Props>();

const { loading, error, redeemVoucher } = useRedemptionApi();

const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const storedData = ref<any>(null);
const stream = ref<MediaStream | null>(null);
const hasCaptured = ref(false);
const capturedImage = ref('');
const submitting = ref(false);
const apiError = ref<string | null>(null);
const cameraError = ref<string | null>(null);

const requiresSignature = computed(() => {
    return (storedData.value?.required_inputs || []).includes('signature');
});

async function startCamera() {
    try {
        cameraError.value = null;
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { 
                facingMode: 'user', 
                width: props.image_config.width, 
                height: props.image_config.height 
            }
        });
        
        if (videoRef.value) {
            videoRef.value.srcObject = stream.value;
        }
    } catch (err: any) {
        console.error('Camera error:', err);
        if (err.name === 'NotAllowedError') {
            cameraError.value = 'Camera access denied. Please allow camera access in your browser settings.';
        } else if (err.name === 'NotFoundError') {
            cameraError.value = 'No camera found on this device.';
        } else {
            cameraError.value = 'Failed to access camera. Please try again.';
        }
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
    
    // Set canvas size to match video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Draw video frame to canvas
    const ctx = canvas.getContext('2d');
    if (ctx) {
        ctx.drawImage(video, 0, 0);
        
        // Convert to base64 with configured quality
        capturedImage.value = canvas.toDataURL(props.image_config.format, props.image_config.quality);
        hasCaptured.value = true;
        
        // Stop camera after capture
        stopCamera();
    }
}

function retakeSelfie() {
    hasCaptured.value = false;
    capturedImage.value = '';
    startCamera();
}

const handleSubmit = async () => {
    if (!capturedImage.value || !storedData.value) return;
    
    apiError.value = null;
    
    // If signature is required, navigate to signature page
    if (requiresSignature.value) {
        // Update stored data with selfie
        const updatedData = {
            ...storedData.value,
            inputs: {
                ...storedData.value.inputs,
                selfie: capturedImage.value,
            },
        };
        
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(updatedData));
        
        // Navigate to signature page
        router.visit(`/redeem/${props.voucher_code}/signature`);
        return;
    }
    
    // Otherwise, proceed to finalize page for confirmation
    try {
        submitting.value = true;
        
        // Update stored data with selfie
        const updatedData = {
            ...storedData.value,
            inputs: {
                ...storedData.value.inputs,
                selfie: capturedImage.value,
            },
        };
        
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(updatedData));
        
        // Navigate to finalize page
        router.visit(`/redeem/${props.voucher_code}/finalize`);
    } catch (err: any) {
        submitting.value = false;
        apiError.value = err.message || 'Failed to proceed. Please try again.';
        console.error('Navigation failed:', err);
    }
};

// Load stored data and start camera when component mounts
onMounted(async () => {
    // Load stored wallet data from sessionStorage
    const stored = sessionStorage.getItem(`redeem_${props.voucher_code}`);
    if (!stored) {
        // No stored data, redirect back to wallet
        router.visit(`/redeem/${props.voucher_code}/wallet`);
        return;
    }
    
    storedData.value = JSON.parse(stored);
    
    // Start camera
    await startCamera();
});

// Cleanup camera when component unmounts
onUnmounted(() => {
    stopCamera();
});
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <!-- Loading State -->
            <div v-if="!storedData" class="flex min-h-[50vh] items-center justify-center">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>

            <template v-else>
                <!-- Error Alert -->
                <Alert v-if="apiError || cameraError" variant="destructive" class="mb-6">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        {{ apiError || cameraError }}
                    </AlertDescription>
                </Alert>

                <!-- Selfie Capture Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Camera class="h-5 w-5" />
                            Selfie Required
                        </CardTitle>
                        <CardDescription>
                            Please take a clear photo of yourself
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="handleSubmit" class="space-y-6">
                            <!-- Camera/Preview Area -->
                            <div class="space-y-4">
                                <!-- Live Camera Feed (before capture) -->
                                <div v-if="!hasCaptured" class="relative rounded-lg border overflow-hidden bg-black">
                                    <video
                                        ref="videoRef"
                                        autoplay
                                        playsinline
                                        class="w-full h-96 object-cover"
                                    ></video>
                                    <div class="absolute inset-0 pointer-events-none">
                                        <!-- Face guide overlay -->
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <div class="w-64 h-80 border-4 border-white/50 rounded-full"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Captured Image Preview -->
                                <div v-else class="relative rounded-lg border overflow-hidden">
                                    <img
                                        :src="capturedImage"
                                        alt="Your selfie"
                                        class="w-full h-96 object-cover"
                                    />
                                </div>

                                <!-- Camera Instructions -->
                                <div v-if="!hasCaptured" class="text-sm text-muted-foreground text-center">
                                    <p>Position your face in the oval guide</p>
                                    <p class="text-xs mt-1">Make sure your face is well-lit and clearly visible</p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-3 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="flex-1"
                                    @click="router.visit(`/redeem/${props.voucher_code}/wallet`)"
                                    :disabled="submitting"
                                >
                                    Back
                                </Button>
                                
                                <!-- Capture Button (before capture) -->
                                <Button
                                    v-if="!hasCaptured"
                                    type="button"
                                    class="flex-1"
                                    @click="captureSelfie"
                                    :disabled="!!cameraError"
                                >
                                    <Camera class="h-4 w-4 mr-2" />
                                    Capture Photo
                                </Button>
                                
                                <!-- Retake & Continue Buttons (after capture) -->
                                <template v-else>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="flex-1"
                                        @click="retakeSelfie"
                                        :disabled="submitting"
                                    >
                                        <RotateCw class="h-4 w-4 mr-2" />
                                        Retake
                                    </Button>
                                    <Button
                                        type="submit"
                                        class="flex-1"
                                        :disabled="submitting"
                                    >
                                        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin mr-2" />
                                        {{ submitting ? 'Processing...' : 'Continue' }}
                                    </Button>
                                </template>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Hidden canvas for image capture -->
                <canvas ref="canvasRef" class="hidden"></canvas>
            </template>
        </div>
    </PublicLayout>
</template>
