<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useCamera } from '../composables/useCamera';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertCircle, Camera, RotateCw } from 'lucide-vue-next';
import CameraPermissionAlert from './CameraPermissionAlert.vue';

export interface SelfieConfig {
    width?: number;
    height?: number;
    quality?: number;
    format?: string;
    facing_mode?: 'user' | 'environment';
    show_guide?: boolean;
}

export interface SelfieData {
    selfie: string;
    width: number;
    height: number;
    format: string;
}

interface Props {
    config?: SelfieConfig;
}

interface Emits {
    (e: 'submit', value: SelfieData): void;
    (e: 'cancel'): void;
}

const props = withDefaults(defineProps<Props>(), {
    config: () => ({}),
});

const emit = defineEmits<Emits>();

const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const cameraAlertRef = ref<InstanceType<typeof CameraPermissionAlert> | null>(null);

const hasCaptured = ref(false);
const capturedImage = ref('');

const { stream, error: cameraError, startCamera, stopCamera } = useCamera();

const width = computed(() => props.config?.width ?? 640);
const height = computed(() => props.config?.height ?? 480);
const quality = computed(() => props.config?.quality ?? 0.85);
const format = computed(() => props.config?.format ?? 'image/jpeg');
const facingMode = computed(() => props.config?.facing_mode ?? 'user');
const showGuide = computed(() => props.config?.show_guide ?? true);

async function initCamera() {
    try {
        const mediaStream = await startCamera({
            video: {
                facingMode: facingMode.value,
                width: width.value,
                height: height.value,
            }
        });
        
        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
        }
    } catch (err: any) {
        if (err.name === 'NotAllowedError') {
            cameraAlertRef.value?.open();
        }
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
        capturedImage.value = canvas.toDataURL(format.value, quality.value);
        hasCaptured.value = true;
        
        // Stop camera after capture
        stopCamera();
    }
}

function retakeSelfie() {
    hasCaptured.value = false;
    capturedImage.value = '';
    initCamera();
}

function handleSubmit() {
    if (!capturedImage.value) return;
    
    const selfieData: SelfieData = {
        selfie: capturedImage.value,
        width: canvasRef.value?.width ?? width.value,
        height: canvasRef.value?.height ?? height.value,
        format: format.value,
    };
    
    emit('submit', selfieData);
}

function handleCancel() {
    emit('cancel');
}

onMounted(() => {
    initCamera();
});
</script>

<template>
    <div class="container mx-auto max-w-2xl px-4 py-8">
        <!-- Error Alert -->
        <Alert v-if="cameraError" variant="destructive" class="mb-6">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                {{ cameraError }}
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
                            <div v-if="showGuide" class="absolute inset-0 pointer-events-none">
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
                            @click="handleCancel"
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
                            >
                                <RotateCw class="h-4 w-4 mr-2" />
                                Retake
                            </Button>
                            <Button
                                type="submit"
                                class="flex-1"
                            >
                                Continue
                            </Button>
                        </template>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Hidden canvas for image capture -->
        <canvas ref="canvasRef" class="hidden"></canvas>
        
        <!-- Camera Permission Alert Modal -->
        <CameraPermissionAlert ref="cameraAlertRef" />
    </div>
</template>
