<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { storePlugin } from '@/actions/App/Http/Controllers/Redeem/RedeemWizardController';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

interface Props {
    voucher_code: string;
    voucher: {
        code: string;
        amount: number;
        currency: string;
    };
    plugin: string;
    requested_fields: string[];
    default_values: Record<string, any>;
}

const props = defineProps<Props>();

const canvas = ref<HTMLCanvasElement | null>(null);
const isDrawing = ref(false);
const hasSignature = ref(false);

const form = useForm({
    signature: '',
});

let ctx: CanvasRenderingContext2D | null = null;

const initCanvas = () => {
    if (!canvas.value) return;

    ctx = canvas.value.getContext('2d');
    if (!ctx) return;

    // Set canvas size
    const rect = canvas.value.getBoundingClientRect();
    canvas.value.width = rect.width * window.devicePixelRatio;
    canvas.value.height = rect.height * window.devicePixelRatio;
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

    // Set drawing style
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
};

const startDrawing = (e: MouseEvent | TouchEvent) => {
    if (!ctx || !canvas.value) return;
    isDrawing.value = true;

    const rect = canvas.value.getBoundingClientRect();
    const x = ('touches' in e ? e.touches[0].clientX : e.clientX) - rect.left;
    const y = ('touches' in e ? e.touches[0].clientY : e.clientY) - rect.top;

    ctx.beginPath();
    ctx.moveTo(x, y);
};

const draw = (e: MouseEvent | TouchEvent) => {
    if (!isDrawing.value || !ctx || !canvas.value) return;
    e.preventDefault();

    hasSignature.value = true;

    const rect = canvas.value.getBoundingClientRect();
    const x = ('touches' in e ? e.touches[0].clientX : e.clientX) - rect.left;
    const y = ('touches' in e ? e.touches[0].clientY : e.clientY) - rect.top;

    ctx.lineTo(x, y);
    ctx.stroke();
};

const stopDrawing = () => {
    isDrawing.value = false;
};

const clearSignature = () => {
    if (!ctx || !canvas.value) return;
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height);
    hasSignature.value = false;
    form.signature = '';
};

const handleSubmit = () => {
    if (!canvas.value || !hasSignature.value) return;

    // Convert canvas to base64 data URL
    form.signature = canvas.value.toDataURL('image/png');

    form.post(
        storePlugin.url({
            voucher: props.voucher_code,
            plugin: props.plugin,
        })
    );
};

// Initialize canvas when component mounts
setTimeout(initCanvas, 100);
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <Card>
                <CardHeader>
                    <CardTitle>Signature Required</CardTitle>
                    <CardDescription>
                        Please sign in the box below using your mouse or touchscreen
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- Signature Canvas -->
                        <div class="space-y-2">
                            <div
                                class="relative rounded-md border-2 border-dashed border-gray-300 bg-white"
                            >
                                <canvas
                                    ref="canvas"
                                    class="h-64 w-full touch-none cursor-crosshair"
                                    @mousedown="startDrawing"
                                    @mousemove="draw"
                                    @mouseup="stopDrawing"
                                    @mouseleave="stopDrawing"
                                    @touchstart="startDrawing"
                                    @touchmove="draw"
                                    @touchend="stopDrawing"
                                />
                                <div
                                    v-if="!hasSignature"
                                    class="pointer-events-none absolute inset-0 flex items-center justify-center text-gray-400"
                                >
                                    Sign here
                                </div>
                            </div>
                            <p v-if="form.errors.signature" class="text-sm text-red-600">
                                {{ form.errors.signature }}
                            </p>
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

                        <!-- Submit Button -->
                        <div class="flex gap-3 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                class="flex-1"
                                @click="$inertia.visit(`/redeem/${voucher_code}/wallet`)"
                                :disabled="form.processing"
                            >
                                Back
                            </Button>
                            <Button
                                type="submit"
                                class="flex-1"
                                :disabled="!hasSignature || form.processing"
                            >
                                {{ form.processing ? 'Processing...' : 'Continue' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
