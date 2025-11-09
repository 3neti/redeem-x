<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useBrowserLocation } from '@/composables/useBrowserLocation';
import { useRedemptionApi } from '@/composables/useRedemptionApi';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertCircle, MapPin } from 'lucide-vue-next';
import GeoPermissionAlert from '@/components/GeoPermissionAlert.vue';

interface Props {
    voucher_code: string;
}

const props = defineProps<Props>();

const { redeemVoucher } = useRedemptionApi();
const apiKey = import.meta.env.VITE_OPENCAGE_KEY || '';
const { location, loading: geoLoading, error: geoError, getLocation } = useBrowserLocation(apiKey, 3 * 60 * 1000);

const storedData = ref<any>(null);
const geoAlertRef = ref<InstanceType<typeof GeoPermissionAlert> | null>(null);
const submitting = ref(false);
const apiError = ref<string | null>(null);
const coordinatesCopied = ref(false);

const parsedLocation = computed(() => {
    return location.value;
});

const formattedAddress = computed(() => {
    return parsedLocation.value?.address?.formatted || '';
});

const staticMapUrl = computed(() => {
    if (!location.value) return '';
    
    const { latitude, longitude } = location.value;
    const zoom = 16;
    const size = 600;
    
    // Using Mapbox Static Images API - free tier (50,000 requests/month)
    // You'll need to set VITE_MAPBOX_TOKEN in .env
    const mapboxToken = import.meta.env.VITE_MAPBOX_TOKEN || '';
    
    console.log('[Location] Mapbox token present?', !!mapboxToken);
    console.log('[Location] Token value:', mapboxToken ? mapboxToken.substring(0, 10) + '...' : 'none');
    
    if (mapboxToken && mapboxToken !== 'your_actual_token_here') {
        const url = `https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/pin-s+ff0000(${longitude},${latitude})/${longitude},${latitude},${zoom},0/${size}x300@2x?access_token=${mapboxToken}`;
        console.log('[Location] Using Mapbox URL:', url.substring(0, 100) + '...');
        return url;
    }
    
    // Fallback: Google Maps static (no API key needed for basic usage)
    const googleUrl = `https://maps.googleapis.com/maps/api/staticmap?center=${latitude},${longitude}&zoom=${zoom}&size=${size}x300&markers=color:red%7C${latitude},${longitude}`;
    console.log('[Location] Using Google Maps URL:', googleUrl);
    return googleUrl;
});

const requiresSelfie = computed(() => {
    return (storedData.value?.required_inputs || []).includes('selfie');
});

const requiresSignature = computed(() => {
    return (storedData.value?.required_inputs || []).includes('signature');
});

async function fetchLocation() {
    const data = await getLocation(false);

    console.log('[Location] Fetched location data:', data);
    console.log('[Location] Formatted address:', data?.address?.formatted);

    if (geoError.value === 'PERMISSION_DENIED') {
        geoAlertRef.value?.open();
        return;
    }

    if (!data) {
        apiError.value = 'Failed to get location. Please try again.';
    } else if (!data.address?.formatted) {
        console.warn('[Location] No formatted address in response');
    }
}

function copyCoordinates() {
    if (!location.value) return;
    
    const coords = `${location.value.latitude.toFixed(6)}, ${location.value.longitude.toFixed(6)}`;
    navigator.clipboard.writeText(coords).then(() => {
        coordinatesCopied.value = true;
        setTimeout(() => {
            coordinatesCopied.value = false;
        }, 2000);
    });
}

const handleSubmit = async () => {
    if (!location.value) {
        apiError.value = 'Please capture your location first.';
        return;
    }

    apiError.value = null;

    // If selfie is required, navigate to selfie page
    if (requiresSelfie.value) {
        // Update stored data with location
        const updatedData = {
            ...storedData.value,
            inputs: {
                ...storedData.value.inputs,
                location: JSON.stringify(location.value),
            },
        };
        
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(updatedData));
        
        // Navigate to selfie page
        router.visit(`/redeem/${props.voucher_code}/selfie`);
        return;
    }

    // If signature is required (but not selfie), navigate to signature page
    if (requiresSignature.value) {
        // Update stored data with location
        const updatedData = {
            ...storedData.value,
            inputs: {
                ...storedData.value.inputs,
                location: JSON.stringify(location.value),
            },
        };
        
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(updatedData));
        
        // Navigate to signature page
        router.visit(`/redeem/${props.voucher_code}/signature`);
        return;
    }

    // Otherwise, proceed with redemption directly
    try {
        submitting.value = true;

        // Combine all inputs including location
        const inputs = {
            ...storedData.value.inputs,
            location: JSON.stringify(location.value),
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
        submitting.value = false;
        apiError.value = err.response?.data?.message || 'Failed to redeem voucher. Please try again.';
        console.error('Redemption failed:', err);
    }
};

// Load stored data and initialize when component mounts
onMounted(() => {
    // Load stored wallet data from sessionStorage
    const stored = sessionStorage.getItem(`redeem_${props.voucher_code}`);
    if (!stored) {
        // No stored data, redirect back to wallet
        router.visit(`/redeem/${props.voucher_code}/wallet`);
        return;
    }

    storedData.value = JSON.parse(stored);
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
                <Alert v-if="apiError" variant="destructive" class="mb-6">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        {{ apiError }}
                    </AlertDescription>
                </Alert>

                <!-- Location Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <MapPin class="h-5 w-5" />
                            Location Required
                        </CardTitle>
                        <CardDescription>
                            Please share your current location to continue with redemption
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="handleSubmit" class="space-y-6">
                            <!-- Get Location Button (shown when no location captured) -->
                            <div v-if="!parsedLocation" class="flex flex-col items-center justify-center py-8 space-y-4">
                                <MapPin class="h-12 w-12 text-muted-foreground" />
                                <p class="text-sm text-muted-foreground text-center">
                                    We need to capture your current location to continue
                                </p>
                                <Button
                                    type="button"
                                    @click="fetchLocation"
                                    :disabled="geoLoading"
                                    size="lg"
                                >
                                    <Loader2 v-if="geoLoading" class="h-4 w-4 animate-spin mr-2" />
                                    {{ geoLoading ? 'Getting Location...' : 'Capture My Location' }}
                                </Button>
                            </div>

                            <!-- Static Map & Location Info (when location captured) -->
                            <div v-if="parsedLocation" class="space-y-4">
                                <!-- Map Image -->
                                <div class="rounded-lg border overflow-hidden">
                                    <img
                                        :src="staticMapUrl"
                                        alt="Map showing your location"
                                        class="w-full h-64 object-cover"
                                        loading="lazy"
                                    />
                                </div>

                                <!-- Location Address & Coordinates -->
                                <div class="space-y-3">
                                    <div class="text-sm font-medium">
                                        {{ formattedAddress }}
                                    </div>
                                    
                                    <!-- Copyable Coordinates -->
                                    <button
                                        type="button"
                                        @click="copyCoordinates"
                                        class="flex items-center gap-2 text-xs text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        <span>{{ parsedLocation.latitude.toFixed(6) }}, {{ parsedLocation.longitude.toFixed(6) }}</span>
                                        <svg v-if="!coordinatesCopied" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else class="h-3 w-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="flex gap-3 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="flex-1"
                                    @click="router.visit(`/redeem/${props.voucher_code}/wallet`)"
                                    :disabled="submitting || geoLoading"
                                >
                                    Back
                                </Button>
                                <Button
                                    type="submit"
                                    class="flex-1"
                                    :disabled="!location || submitting || geoLoading"
                                >
                                    <Loader2 v-if="submitting" class="h-4 w-4 animate-spin mr-2" />
                                    {{ submitting ? 'Processing...' : 'Continue' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Geo Permission Alert Modal -->
                <GeoPermissionAlert ref="geoAlertRef" />
            </template>
        </div>
    </PublicLayout>
</template>
