<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, PenTool } from 'lucide-vue-next';

export interface SignatureConfig {
    width?: number;
    height?: number;
    quality?: number;
    format?: string;
    line_width?: number;
    line_color?: string;
    line_cap?: 'butt' | 'round' | 'square';
    line_join?: 'bevel' | 'round' | 'miter';
}

export interface SignatureData {
    image: string;
    width: number;
    height: number;
    format: string;
}

interface Props {
    config?: SignatureConfig;
}

interface Emits {
    (e: 'submit', value: SignatureData): void;
    (e: 'cancel'): void;
}

const props = withDefaults(defineProps<Props>(), {
    config: () => ({}),
});

const emit = defineEmits<Emits>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const isDrawing = ref(false);
const hasSignature = ref(false);

const width = computed(() => props.config?.width ?? 600);
const height = computed(() => props.config?.height ?? 256);
const quality = computed(() => props.config?.quality ?? 0.85);
const format = computed(() => props.config?.format ?? 'image/png');
const lineWidth = computed(() => props.config?.line_width ?? 2);
const lineColor = computed(() => props.config?.line_color ?? '#000000');
const lineCap = computed(() => props.config?.line_cap ?? 'round');
const lineJoin = computed(() => props.config?.line_join ?? 'round');

let ctx: CanvasRenderingContext2D | null = null;

function initCanvas() {
    if (!canvasRef.value) return;

    ctx = canvasRef.value.getContext('2d');
    if (!ctx) return;

    // Set canvas size with device pixel ratio for high-DPI displays
    const rect = canvasRef.value.getBoundingClientRect();
    canvasRef.value.width = rect.width * window.devicePixelRatio;
    canvasRef.value.height = rect.height * window.devicePixelRatio;
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

    // Set drawing style from config
    ctx.strokeStyle = lineColor.value;
    ctx.lineWidth = lineWidth.value;
    ctx.lineCap = lineCap.value;
    ctx.lineJoin = lineJoin.value;
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
    ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
    hasSignature.value = false;
}

function handleSubmit() {
    if (!canvasRef.value || !hasSignature.value) return;

    // Convert canvas to base64 data URL with configured quality
    const image = canvasRef.value.toDataURL(format.value, quality.value);

    const signatureData: SignatureData = {
        image,
        width: canvasRef.value.width,
        height: canvasRef.value.height,
        format: format.value,
    };

    emit('submit', signatureData);
}

function handleCancel() {
    emit('cancel');
}

onMounted(() => {
    setTimeout(initCanvas, 100);
});
</script>

<template>
    <div class="container mx-auto max-w-2xl px-4 py-8">
        <!-- Signature Capture Card -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <PenTool class="h-5 w-5" />
                    Signature Required
                </CardTitle>
                <CardDescription>
                    Please sign in the box below using your mouse or touchscreen
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <!-- Signature Canvas -->
                    <div class="space-y-2">
                        <div class="relative rounded-md border-2 border-dashed border-gray-300 bg-white">
                            <canvas
                                ref="canvasRef"
                                :style="{ width: width + 'px', height: height + 'px' }"
                                class="w-full touch-none cursor-crosshair"
                                @mousedown="startDrawing"
                                @mousemove="draw"
                                @mouseup="stopDrawing"
                                @mouseleave="stopDrawing"
                                @touchstart.prevent="startDrawing"
                                @touchmove.prevent="draw"
                                @touchend="stopDrawing"
                            />
                            <div
                                v-if="!hasSignature"
                                class="pointer-events-none absolute inset-0 flex items-center justify-center text-gray-400"
                            >
                                Sign here
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="clearSignature"
                            :disabled="!hasSignature"
                        >
                            Clear Signature
                        </Button>
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
                        <Button
                            type="submit"
                            class="flex-1"
                            :disabled="!hasSignature"
                        >
                            Continue
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
