<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useRedemptionApi } from '@/composables/useRedemptionApi';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertCircle } from 'lucide-vue-next';

interface Props {
    voucher_code: string;
}

const props = defineProps<Props>();

const { loading, error, redeemVoucher } = useRedemptionApi();

const canvas = ref<HTMLCanvasElement | null>(null);
const isDrawing = ref(false);
const hasSignature = ref(false);
const signature = ref('');
const storedData = ref<any>(null);

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
    signature.value = '';
};

const handleSubmit = async () => {
    if (!canvas.value || !hasSignature.value || !storedData.value) return;

    // Convert canvas to base64 data URL
    signature.value = canvas.value.toDataURL('image/png');

    try {
        // Combine stored wallet data with signature in inputs
        const inputs = {
            ...storedData.value.inputs,
            signature: signature.value,
        };

        const result = await redeemVoucher({
            code: props.voucher_code,
            mobile: storedData.value.mobile,
            country: storedData.value.country,
            secret: storedData.value.secret,
            bank_code: storedData.value.bank_code,
            account_number: storedData.value.account_number,
            inputs: Object.keys(inputs).length > 0 ? inputs : undefined,
        });

        // Clear stored data
        sessionStorage.removeItem(`redeem_${props.voucher_code}`);

        // Navigate to success page with result
        router.visit(`/redeem/${result.voucher.code}/success`, {
            method: 'get',
            data: {
                amount: result.voucher.amount,
                currency: result.voucher.currency,
                mobile: storedData.value.mobile,
                message: result.message,
                rider: result.rider,
            },
        });
    } catch (err: any) {
        // Handle errors - will be shown via error ref from composable
        console.error('Redemption failed:', err);
    }
};

// Load stored data and initialize canvas when component mounts
onMounted(() => {
    // Load stored wallet data from sessionStorage
    const stored = sessionStorage.getItem(`redeem_${props.voucher_code}`);
    if (!stored) {
        // No stored data, redirect back to wallet
        router.visit(`/redeem/${props.voucher_code}/wallet`);
        return;
    }
    
    storedData.value = JSON.parse(stored);
    
    // Initialize canvas
    setTimeout(initCanvas, 100);
});
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <!-- Loading State -->
            <div v-if="loading" class="flex min-h-[50vh] items-center justify-center">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>

            <!-- Error Alert -->
            <Alert v-if="error" variant="destructive" class="mb-6">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    {{ error }}
                </AlertDescription>
            </Alert>

            <Card v-if="!loading">
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

                        <!-- Submit Button -->
                        <div class="flex gap-3 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                class="flex-1"
                                @click="router.visit(`/redeem/${props.voucher_code}/wallet`)"
                                :disabled="loading"
                            >
                                Back
                            </Button>
                            <Button
                                type="submit"
                                class="flex-1"
                                :disabled="!hasSignature || loading"
                            >
                                {{ loading ? 'Processing...' : 'Continue' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
